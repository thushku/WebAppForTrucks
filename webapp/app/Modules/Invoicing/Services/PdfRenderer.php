<?php

namespace App\Modules\Invoicing\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A document as a PDF (ported from Clinic Ledger): the same HTML the browser
 * prints, printed by a headless Chromium on the server. No npm package —
 * the Chrome, Edge or Chromium already installed is driven directly with
 * --print-to-pdf; the page's own @page rule sets the paper. Indian scripts
 * and Arabic shape correctly this way, which PHP PDF libraries do not.
 */
class PdfRenderer
{
    /** Where a Chromium-based browser usually lives. */
    private const CANDIDATES = [
        '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/microsoft-edge',
        'C:\Program Files\Google\Chrome\Application\chrome.exe', 'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe', 'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
    ];

    /** Playwright's Chromium (cloud and test machines). */
    private const GLOBS = ['/opt/pw-browsers/chromium-*/chrome-linux/chrome', '/root/.cache/ms-playwright/chromium-*/chrome-linux/chrome'];

    private static ?string $binary = null;

    private static bool $looked = false;

    public static function binary(): ?string
    {
        if (self::$looked) {
            return self::$binary;
        }
        self::$looked = true;

        if ($configured = config('documents.chrome_path')) {
            return self::$binary = is_file($configured) ? $configured : null;
        }
        foreach (self::CANDIDATES as $path) {
            if (is_file($path)) {
                return self::$binary = $path;
            }
        }
        foreach (self::GLOBS as $glob) {
            foreach (glob($glob) ?: [] as $path) {
                return self::$binary = $path;
            }
        }

        return self::$binary = null;
    }

    public static function reset(): void
    {
        self::$looked = false;
        self::$binary = null;
    }

    public static function available(): bool
    {
        $driver = config('documents.pdf_driver', 'auto');

        return $driver !== 'browser' && ($driver === 'chromium' || self::binary() !== null);
    }

    /** Self-contained HTML to PDF bytes. */
    public function fromHtml(string $html): string
    {
        $binary = self::binary() ?? throw new RuntimeException('No Chromium-based browser is available for PDFs. Set DOCUMENTS_CHROME_PATH or install Chrome or Chromium.');

        $dir = storage_path('app/private/pdf');
        File::ensureDirectoryExists($dir);
        $id  = Str::uuid()->toString();
        $in  = $dir . DIRECTORY_SEPARATOR . $id . '.html';
        $out = $dir . DIRECTORY_SEPARATOR . $id . '.pdf';
        File::put($in, $html);

        try {
            $process = new Process([
                $binary, '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-extensions', '--disable-dev-shm-usage',
                '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--run-all-compositor-stages-before-draw',
                '--virtual-time-budget=4000', '--user-data-dir=' . $dir . DIRECTORY_SEPARATOR . 'profile', '--no-pdf-header-footer',
                '--print-to-pdf=' . $out, 'file://' . (str_starts_with($in, '/') ? '' : '/') . str_replace('\\', '/', $in),
            ], null, null, null, (float) config('documents.pdf_timeout', 45));
            $process->run();

            if (! is_file($out) || filesize($out) < 100) {
                throw new RuntimeException('The PDF could not be produced: ' . trim($process->getErrorOutput() ?: $process->getOutput() ?: 'no output from the browser'));
            }

            return File::get($out);
        } finally {
            File::delete([$in, $out]);
        }
    }
}
