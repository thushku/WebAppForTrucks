<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\DocumentCounter;
use App\Modules\Settings\Models\DocumentSequence;
use App\Modules\Tax\Models\TaxRegistration;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Document numbers, ported from Clinic Ledger (blueprint 10 §4).
 *
 * Tokens in a format:
 *   {PREFIX}            the sequence's prefix
 *   {FY} / {FY2}        financial year "2026-27" / "26-27"
 *   {YYYY} {YY} {MM} {DD}  the document's date
 *   {BR}                branch code
 *   {SHP} {FRM} {TO}    shipper, loading and unloading place codes (trip numbers, P1)
 *   {0001}              the counter; the token's width sets the padding ({1} pads nothing)
 *
 * `preview()` shows the next number without taking it; `assign()` takes it
 * under a row lock inside the issuing transaction, so two documents issued in
 * the same second never share a number and a void keeps its number.
 *
 * Unlike Clinic Ledger, which keeps one counter and reads back the highest
 * number issued in an older year, every period here has its own counter row:
 * a bill dated 31 March and issued in April continues March's year, and the
 * daily trip serial restarts each day without scanning the trips table.
 */
class DocumentNumbering
{
    /**
     * Where each kind of document keeps its number, so a number already on a
     * document is never handed out again (after a counter is put back by
     * hand). Tables join this list as their modules are built.
     *
     * @var array<string, array{0: string, 1: string}> doc_type => [table, column]
     */
    public const DOCUMENTS = [
        'trip'            => ['trips', 'trip_number'],
        'lr'              => ['consignments', 'lr_number'],
        'invoice'         => ['invoices', 'number'],
        'credit_note'     => ['invoices', 'number'],
        'debit_note'      => ['invoices', 'number'],
        'receipt'         => ['receipts', 'number'],
        'payment_voucher' => ['vouchers', 'number'],
        'hire_challan'    => ['hire_challans', 'number'],
        'quotation'       => ['quotations', 'number'],
        'fuel_slip'       => ['fuel_slips', 'number'],
        'journal'         => ['vouchers', 'number'],
        'voucher'         => ['vouchers', 'number'],
        'complaint'       => ['complaints', 'number'],
        'service_job'     => ['service_jobs', 'number'],
    ];

    /** Sample values for the context tokens, used by previews. */
    public const SAMPLE_PARTS = ['SHP' => 'ACC', 'FRM' => 'COK', 'TO' => 'BOM', 'BR' => 'HO'];

    private const TOKENS = ['PREFIX', 'FY', 'FY2', 'YYYY', 'YY', 'MM', 'DD', 'BR', 'SHP', 'FRM', 'TO'];

    /** @var array<string, bool> */
    private array $tableExists = [];

    public function __construct(private CompanyContext $context) {}

    /**
     * The sequence for a document type, created from the product default the
     * first time. A branch's or a GSTIN's own series (M30) starts as a copy of
     * the company-wide one with the branch code in its prefix, so the series
     * never print the same number.
     */
    public function sequence(string $docType, string $scopeType = 'company', int $scopeId = 0): DocumentSequence
    {
        $default = DocumentSequence::DOC_TYPES[$docType] ?? throw new RuntimeException("Unknown document type {$docType}.");
        if ($scopeType !== 'company') {
            $existing = DocumentSequence::where(['doc_type' => $docType, 'scope_type' => $scopeType, 'scope_id' => $scopeId])->first();
            if ($existing) {
                return $existing;
            }
            $base = $this->sequence($docType);

            return DocumentSequence::create([
                'doc_type' => $docType, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
                'prefix' => $this->scopedPrefix($base, $this->scopeCode($scopeType, $scopeId)),
                'format' => $base->format, 'reset_policy' => $base->reset_policy, 'start_number' => 1, 'max_length' => $base->max_length,
            ]);
        }

        return DocumentSequence::firstOrCreate(
            ['doc_type' => $docType, 'scope_type' => $scopeType, 'scope_id' => $scopeId],
            [
                'prefix'       => $default[1],
                'format'       => $default[2],
                'reset_policy' => $default[3],
                'start_number' => 1,
                'max_length'   => $this->maxLengthFor($docType),
            ],
        );
    }

    /** The number the next document would get, without consuming it. */
    public function preview(DocumentSequence $sequence, ?CarbonInterface $on = null, array $parts = []): string
    {
        $on = $this->localDate($on);

        return $this->render($sequence, $this->peekNumber($sequence, $on), $on, $parts + self::SAMPLE_PARTS);
    }

    /** The counter the next document of a period would get. */
    public function peekNumber(DocumentSequence $sequence, ?CarbonInterface $on = null): int
    {
        $on      = $this->localDate($on);
        $counter = DocumentCounter::where('document_sequence_id', $sequence->id)->where('period_key', $this->periodKey($sequence, $on))->first();

        return $counter?->next_number ?? max(1, $sequence->start_number);
    }

    /**
     * Take the next number. Call inside the issuing transaction.
     *
     * @param array<string, string> $parts values for {SHP} {FRM} {TO} {BR}
     */
    public function assign(string $docType, ?CarbonInterface $on = null, array $parts = [], string $scopeType = 'company', int $scopeId = 0): string
    {
        $on = $this->localDate($on);

        return DB::transaction(function () use ($docType, $on, $parts, $scopeType, $scopeId) {
            $sequence = $this->sequence($docType, $scopeType, $scopeId);
            $sequence = DocumentSequence::whereKey($sequence->id)->lockForUpdate()->firstOrFail();
            $period   = $this->periodKey($sequence, $on);

            $counter = DocumentCounter::where('document_sequence_id', $sequence->id)->where('period_key', $period)->lockForUpdate()->first()
                ?? DocumentCounter::create(['document_sequence_id' => $sequence->id, 'period_key' => $period, 'next_number' => max(1, $sequence->start_number)]);

            $number = $counter->next_number;
            $text   = $this->render($sequence, $number, $on, $parts);

            // Never twice: a counter put back by hand skips past numbers already used.
            for ($guard = 0; $this->isTaken($docType, $text); $guard++) {
                if ($guard > 10000) {
                    throw new RuntimeException("No free number for {$docType} after {$text}: check the numbering format.");
                }
                $text = $this->render($sequence, ++$number, $on, $parts);
            }

            if ($sequence->max_length && mb_strlen($text) > $sequence->max_length) {
                throw ValidationException::withMessages(['number' => __('The number :number is longer than :max characters. Shorten the prefix or format in Settings → Numbering.', ['number' => $text, 'max' => $sequence->max_length])]);
            }

            $counter->forceFill(['next_number' => $number + 1, 'last_assigned_at' => now()])->save();

            return $text;
        });
    }

    /**
     * Take the next number of a document that belongs to a branch: the
     * branch's own series when the company numbers this type per branch, else
     * the company's; {BR} is the branch's code either way.
     */
    public function assignInBranch(string $docType, ?CarbonInterface $on, ?int $branchId, array $parts = []): string
    {
        $parts['BR'] ??= $this->branchCode($branchId);
        $perBranch = $branchId && in_array($docType, DocumentSequence::BRANCH_TYPES, true) && $this->sequence($docType)->per_branch;

        return $this->assign($docType, $on, $parts, $perBranch ? 'branch' : 'company', $perBranch ? $branchId : 0);
    }

    /** A branch's code for {BR}: the head office's when there is no branch, else "HO". */
    public function branchCode(?int $branchId): string
    {
        $branch = $branchId ? Branch::find($branchId) : Branch::where('is_head_office', true)->first();

        return $branch?->code ?: 'HO';
    }

    /** The code a branch's or a GSTIN's series carries: the branch code, else the GSTIN's state code. */
    public function scopeCode(string $scopeType, int $scopeId): string
    {
        return match ($scopeType) {
            'branch' => $this->branchCode($scopeId),
            'registration' => ($registration = TaxRegistration::find($scopeId))
                ? ($registration->branch_id ? $this->branchCode($registration->branch_id) : (string) ($registration->state_code ?: substr((string) $registration->tax_id, 0, 2)))
                : 'XX',
            default => '',
        };
    }

    /**
     * "BOM/LR/" for a branch's LRs; for a GSTIN's tax invoices the code goes
     * in front of the type ("BOM/", "BOMCN"), without the slash and then
     * shortened until the widest number fits the 16 characters a tax invoice
     * may have.
     */
    private function scopedPrefix(DocumentSequence $base, string $code): string
    {
        $code = self::code($code);
        if (! in_array($base->doc_type, DocumentSequence::TAX_INVOICE_TYPES, true)) {
            return mb_substr($code . '/' . $base->prefix, 0, 20);
        }
        $type = $base->doc_type === 'invoice' ? '' : rtrim(strtoupper((string) DocumentSequence::DOC_TYPES[$base->doc_type][1]), '/');
        foreach ([6, 3, 2] as $width) {
            foreach (['/', ''] as $slash) {
                $prefix = substr($code, 0, $width) . $type . $slash;
                $draft = $base->replicate()->fill(['prefix' => $prefix]);
                if (! $base->max_length || mb_strlen($this->render($draft, 99999, now()->addYears(8), self::SAMPLE_PARTS)) <= $base->max_length) {
                    return $prefix;
                }
            }
        }

        return $prefix;
    }

    /** Set where the current period's counter continues (Settings → Numbering). */
    public function setNext(DocumentSequence $sequence, int $next, ?CarbonInterface $on = null): void
    {
        $period = $this->periodKey($sequence, $this->localDate($on));

        DocumentCounter::updateOrCreate(
            ['document_sequence_id' => $sequence->id, 'period_key' => $period],
            ['next_number' => max(1, $next)],
        );
    }

    public function render(DocumentSequence $sequence, int $number, CarbonInterface $on, array $parts = []): string
    {
        $fy     = $this->fiscalYear($on);
        $values = [
            '{PREFIX}' => (string) $sequence->prefix,
            '{FY}'     => $fy->key,
            '{FY2}'    => self::shortFy($fy),
            '{YYYY}'   => $on->format('Y'),
            '{YY}'     => $on->format('y'),
            '{MM}'     => $on->format('m'),
            '{DD}'     => $on->format('d'),
        ];
        foreach (['BR', 'SHP', 'FRM', 'TO'] as $token) {
            $values['{' . $token . '}'] = self::code($parts[$token] ?? 'XXX');
        }

        $out = strtr((string) $sequence->format, $values);

        return preg_replace_callback('/\{(0*1)\}/', fn ($m) => str_pad((string) $number, strlen($m[1]), '0', STR_PAD_LEFT), $out) ?? $out;
    }

    /**
     * A format must keep exactly one counter, only known tokens, and between
     * them only letters, digits and - / _ . (GST Rule 46 allows "-" and "/";
     * `allowedCharacters` narrows a tax invoice to those).
     */
    public static function isValidFormat(string $format, string $allowedCharacters = 'A-Za-z0-9\/\-_.'): bool
    {
        if (preg_match_all('/\{0*1\}/', $format) !== 1) {
            return false;
        }

        $rest = (string) preg_replace('/\{(?:' . implode('|', self::TOKENS) . '|0*1)\}/', '', $format);

        return (bool) preg_match('/^[' . $allowedCharacters . ']*$/', $rest);
    }

    /** The characters a prefix and format may hold for a document type. */
    public static function allowedCharacters(string $docType): string
    {
        return in_array($docType, DocumentSequence::TAX_INVOICE_TYPES, true) ? 'A-Za-z0-9\/\-' : 'A-Za-z0-9\/\-_.';
    }

    /** "2026-27", "2026-10", "2026-10-25" or "all" — the period a date numbers in. */
    public function periodKey(DocumentSequence $sequence, CarbonInterface $on): string
    {
        return match ($sequence->reset_policy) {
            'daily'   => $on->format('Y-m-d'),
            'monthly' => $on->format('Y-m'),
            'never'   => 'all',
            default   => $this->fiscalYear($on)->key,
        };
    }

    public function fiscalYear(CarbonInterface $on): FiscalYear
    {
        return FiscalYear::containing($on, (int) ($this->context->company()->fy_start_month ?: 1));
    }

    /** The longest number allowed: India's tax invoices, credit and debit notes take 16 (GST Rule 46). */
    public function maxLengthFor(string $docType): ?int
    {
        return $this->context->company()->country === 'IN' && in_array($docType, DocumentSequence::TAX_INVOICE_TYPES, true) ? 16 : null;
    }

    /** "2026-27" → "26-27"; a calendar year "2026" stays "2026". */
    public static function shortFy(FiscalYear $fy): string
    {
        return str_contains($fy->key, '-') ? substr($fy->key, 2) : $fy->key;
    }

    /** A code as it may appear inside a number: letters and digits, upper case, at most 6. */
    public static function code(string $value): string
    {
        return substr(strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value)), 0, 6) ?: 'XXX';
    }

    /** The document's date in the company's time zone (a trip at 01:00 IST belongs to that day, not the UTC one). */
    private function localDate(?CarbonInterface $on): CarbonImmutable
    {
        return CarbonImmutable::instance($on ?? now())->setTimezone($this->context->company()->timezone ?: 'UTC');
    }

    private function isTaken(string $docType, string $number): bool
    {
        [$table, $column] = self::DOCUMENTS[$docType] ?? [null, null];

        if ($table === null || ! ($this->tableExists[$table] ??= Schema::hasTable($table))) {
            return false;
        }

        return DB::table($table)->where('company_id', $this->context->id())->where($column, $number)->lockForUpdate()->exists();
    }
}
