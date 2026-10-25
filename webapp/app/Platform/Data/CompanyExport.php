<?php

namespace App\Platform\Data;

use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Everything a company has, in one ZIP (blueprint 09 §8, "your data stays
 * yours"): one CSV per table of the company's data — bank and ID numbers
 * readable, secrets (codes, tokens) left out — its uploaded files, and a
 * read-me. The same file, encrypted, is the copy kept for the law when a
 * company is deleted.
 */
class CompanyExport
{
    /** Tables that are only machinery or hold secrets: not part of "your data". */
    private const SKIP_TABLES = ['activation_codes', 'api_commands', 'document_links', 'document_counters', 'document_sequences', 'company_settings'];

    /** Columns never written out: one-time codes, links that open documents, photo fingerprints. */
    private const SKIP_COLUMNS = ['code_hash', 'token', 'token_hash', 'public_token', 'ahash', 'poll_hash', 'push_token'];

    /** Encrypted in the database (the models' "encrypted" casts): written out readable. */
    private const ENCRYPTED = [
        'party_bank_accounts' => ['account_number', 'iban', 'upi_id'],
        'company_bank_accounts' => ['account_number', 'iban', 'upi_id'],
        'people' => ['id_number', 'bank_account_number', 'bank_iban', 'upi_id'],
    ];

    /**
     * Writes the ZIP to a path on the local disk and returns that path. With
     * a password the entries are AES-256 encrypted.
     *
     * @return array{path: string, tables: array<string, int>, files: int}
     */
    public function build(Company $company, string $diskPath, ?string $password = null): array
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($diskPath));
        $zipFile = $disk->path($diskPath);
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot write {$zipFile}.");
        }
        $temp = [];
        $counts = [];

        try {
            // The company itself, then every table of its data.
            $temp[] = $file = $this->csv(DB::table('companies')->where('id', $company->id)->get()->map(fn ($r) => collect((array) $r)->except(['retention_path'])->all())->all());
            $zip->addFile($file, 'data/company.csv');
            $members = DB::table('company_users as m')->join('accounts as a', 'a.id', '=', 'm.account_id')->where('m.company_id', $company->id)
                ->get(['m.id', 'a.name', 'a.email', 'a.mobile', 'm.title', 'm.status', 'm.joined_at', 'm.last_seen_at'])->map(fn ($r) => (array) $r)->all();
            $temp[] = $file = $this->csv($members);
            $zip->addFile($file, 'data/team.csv');

            foreach ($this->tables() as $table) {
                $temp[] = $file = tempnam(sys_get_temp_dir(), 'exp');
                $counts[$table] = $this->tableCsv($table, (int) $company->id, $file);
                $zip->addFile($file, "data/{$table}.csv");
                $this->encrypt($zip, "data/{$table}.csv", $password);
            }
            $this->encrypt($zip, 'data/company.csv', $password);
            $this->encrypt($zip, 'data/team.csv', $password);

            // Every uploaded file: documents, road photos, the logo.
            $files = 0;
            foreach ($disk->allFiles("companies/{$company->uuid}") as $path) {
                $name = 'files/' . substr($path, strlen("companies/{$company->uuid}/"));
                $zip->addFile($disk->path($path), $name);
                $this->encrypt($zip, $name, $password);
                $files++;
            }

            $zip->addFromString('README.txt', $this->readme($company, $counts, $files));
            $this->encrypt($zip, 'README.txt', $password);
            $zip->close();
        } finally {
            foreach ($temp as $file) {
                @unlink($file);
            }
        }

        return ['path' => $diskPath, 'tables' => $counts, 'files' => $files];
    }

    /** @return string[] the company's tables, from the database itself, so a new table is never forgotten */
    public function tables(): array
    {
        return collect(DB::select("select table_name as t from information_schema.columns where table_schema = database() and column_name = 'company_id' order by table_name"))
            ->pluck('t')->reject(fn ($t) => in_array($t, self::SKIP_TABLES, true))->values()->all();
    }

    private function tableCsv(string $table, int $companyId, string $file): int
    {
        $out = fopen($file, 'w');
        fwrite($out, "\u{FEFF}");
        $columns = collect(DB::select('select column_name as c from information_schema.columns where table_schema = database() and table_name = ? order by ordinal_position', [$table]))
            ->pluck('c')->reject(fn ($c) => in_array($c, self::SKIP_COLUMNS, true))->values()->all();
        fputcsv($out, $columns, ',', '"', '');
        $encrypted = self::ENCRYPTED[$table] ?? [];
        $rows = 0;
        $order = in_array('id', $columns, true) ? 'id' : $columns[0];
        DB::table($table)->where('company_id', $companyId)->select($columns)->orderBy($order)->chunk(2000, function ($chunk) use ($out, $encrypted, &$rows) {
            foreach ($chunk as $row) {
                $values = (array) $row;
                foreach ($encrypted as $column) {
                    if (! empty($values[$column])) {
                        try {
                            $values[$column] = Crypt::decryptString($values[$column]);
                        } catch (\Throwable) {
                            // Left as it is: not encrypted with this key.
                        }
                    }
                }
                fputcsv($out, array_map(fn ($v) => $this->cell($v), $values), ',', '"', '');
                $rows++;
            }
        });
        fclose($out);

        return $rows;
    }

    private function csv(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'exp');
        $out = fopen($file, 'w');
        fwrite($out, "\u{FEFF}");
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $this->cell($v), array_values($row)), ',', '"', '');
            }
        }
        fclose($out);

        return $file;
    }

    /** A spreadsheet must not run a cell as a formula (= + - @ at the start). */
    private function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value) ? "'" . $value : $value;
    }

    private function encrypt(ZipArchive $zip, string $name, ?string $password): void
    {
        if ($password !== null) {
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password);
        }
    }

    private function readme(Company $company, array $counts, int $files): string
    {
        $lines = [
            "{$company->name} — all data",
            'Made ' . now()->toIso8601String(),
            '',
            'data/   one CSV file per table (UTF-8, comma-separated; opens in Excel or LibreOffice).',
            '        company.csv is the company itself, team.csv the people who sign in.',
            '        Amounts are in ' . $company->currency . '. Times are UTC.',
            'files/  every uploaded file: documents (documents/), road photos (road/), the logo.',
            '',
            'Rows per table:',
        ];
        foreach ($counts as $table => $n) {
            $lines[] = sprintf('  %-32s %d', $table, $n);
        }
        $lines[] = sprintf('  %-32s %d', 'files', $files);

        return implode("\r\n", $lines) . "\r\n";
    }
}
