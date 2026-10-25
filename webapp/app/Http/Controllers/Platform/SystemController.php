<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The platform's housekeeping (blueprint 04 §4): storage used per company
 * against its plan, files on disk that no row points to any more (removed
 * safely — screens never show a broken image), the full database export,
 * and the announcement shown to every company.
 */
class SystemController extends Controller
{
    /** Every upload lives under companies/<uuid>/ (documents, road photos, the logo). */
    private const ROOT = 'companies';

    public function __construct(private AuditRecorder $audit, private PlatformSettings $settings) {}

    public function index(): View
    {
        $files = DB::table('document_files')->groupBy('company_id')->selectRaw('company_id, count(*) as n, sum(bytes) as bytes')->get()->keyBy('company_id');
        $photos = DB::table('event_photos')->groupBy('company_id')->selectRaw('company_id, count(*) as n, sum(bytes) as bytes')->get()->keyBy('company_id');
        $companies = Company::with('plan:id,name,storage_mb')->orderBy('name')->get()->map(fn (Company $c) => (object) [
            'company' => $c, 'files' => (int) ($files[$c->id]->n ?? 0) + (int) ($photos[$c->id]->n ?? 0),
            'mb' => round(((int) ($files[$c->id]->bytes ?? 0) + (int) ($photos[$c->id]->bytes ?? 0)) / 1048576, 1),
        ])->sortByDesc('mb')->values();

        return view('platform.system', [
            'companies' => $companies, 'orphans' => count($this->orphans()), 'announcement' => $this->settings->get('announcement'),
            'database' => DB::connection()->getDatabaseName(), 'dump' => (bool) $this->dumpBinary(),
        ]);
    }

    /** Files on disk that no document or photo row points to (left by a crash mid-upload, or by an old version). */
    public function prune(): RedirectResponse
    {
        $orphans = $this->orphans();
        foreach ($orphans as $path) {
            Storage::disk('local')->delete($path);
        }
        $this->audit->record('storage.pruned', 'Removed ' . count($orphans) . ' files no row pointed to', 'platform', null, severity: 'notable');

        return back()->with('status', trans_choice(':count file removed.|:count files removed.', count($orphans)));
    }

    /** The whole database as a compressed SQL file (mysqldump), streamed, never kept on the server. */
    public function export(): StreamedResponse|RedirectResponse
    {
        $binary = $this->dumpBinary();
        if (! $binary) {
            return back()->with('error', __('mysqldump is not installed on this server.'));
        }
        $c = config('database.connections.' . config('database.default'));
        $this->audit->record('data.exported', 'Exported the whole database (console)', 'platform', null, severity: 'sensitive');

        return response()->streamDownload(function () use ($binary, $c) {
            $command = [$binary, '--single-transaction', '--routines', '--no-tablespaces', '-h', (string) $c['host'], '-P', (string) $c['port'], '-u', (string) $c['username'], (string) $c['database']];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['MYSQL_PWD' => (string) $c['password'], 'PATH' => getenv('PATH') ?: '/usr/bin:/bin']);
            if (! is_resource($process)) {
                return;
            }
            $gz = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], 1048576);
                if ($chunk !== false && $chunk !== '') {
                    echo deflate_add($gz, $chunk, ZLIB_NO_FLUSH);
                }
            }
            echo deflate_add($gz, '', ZLIB_FINISH);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }, $c['database'] . '-' . now()->format('Ymd-His') . '.sql.gz', ['Content-Type' => 'application/gzip']);
    }

    public function announce(Request $request): RedirectResponse
    {
        $data = $request->validate(['announcement' => ['nullable', 'string', 'max:300']]);
        $text = trim(strip_tags((string) ($data['announcement'] ?? ''))) ?: null;
        $this->settings->set('announcement', $text);
        $this->audit->record('platform.announced', $text ? 'Announcement: ' . $text : 'Announcement removed', 'platform', null);

        return back()->with('status', $text ? __('Every company now sees the announcement.') : __('Announcement removed.'));
    }

    /**
     * Paths on the local disk with no row. A file younger than an hour is
     * left alone: an upload writes the file a moment before its row.
     *
     * @return string[]
     */
    private function orphans(): array
    {
        $known = DB::table('document_files')->pluck('path')->merge(DB::table('event_photos')->pluck('path'))->merge(DB::table('companies')->whereNotNull('logo_path')->pluck('logo_path'))->flip();
        $disk = Storage::disk('local');
        $before = now()->subHour()->getTimestamp();

        return collect($disk->allFiles(self::ROOT))->reject(fn ($path) => isset($known[$path]) || $disk->lastModified($path) > $before)->values()->all();
    }

    private function dumpBinary(): ?string
    {
        foreach (['/usr/bin/mariadb-dump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
