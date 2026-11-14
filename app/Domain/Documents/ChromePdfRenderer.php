<?php

namespace App\Domain\Documents;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Prints permit documents with Google Chrome (or Chromium) installed on the
 * server, run headless for each document. Chromium shapes Ethiopic text
 * correctly (spec §8), and this needs nothing else running: no Docker, no
 * separate PDF service.
 *
 * The page size and margins come from the template's own `@page` rule (A4
 * landscape, no margin) and its colours print because the template asks for
 * them (`print-color-adjust: exact`). Each run has its own folder for the
 * page, the PDF and Chrome's profile, removed afterwards.
 */
final class ChromePdfRenderer implements PdfRenderer
{
    public function __construct(
        private readonly string $binary,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function render(string $html): string
    {
        $folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'heavy-pdf-'.Str::uuid7();
        File::ensureDirectoryExists($folder.DIRECTORY_SEPARATOR.'profile', 0700);

        $page = $folder.DIRECTORY_SEPARATOR.'index.html';
        $output = $folder.DIRECTORY_SEPARATOR.'document.pdf';
        File::put($page, $html);

        try {
            $result = Process::timeout($this->timeoutSeconds)
                // Chrome writes its crash reports under HOME, which the web server's user may not have.
                ->env(['HOME' => $folder])
                ->run([
                    $this->binary,
                    '--headless=new',
                    // The sandbox needs privileges the web server's user usually lacks. The page is
                    // the permit template: it has no scripts and loads nothing from the network.
                    '--no-sandbox',
                    '--disable-gpu',
                    '--disable-dev-shm-usage',
                    '--disable-extensions',
                    '--no-first-run',
                    '--no-default-browser-check',
                    '--hide-scrollbars',
                    '--no-pdf-header-footer',
                    '--user-data-dir='.$folder.DIRECTORY_SEPARATOR.'profile',
                    '--print-to-pdf='.$output,
                    self::fileUrl($page),
                ]);

            if (! $result->successful()) {
                throw new RuntimeException("Chrome stopped with exit code {$result->exitCode()}: ".Str::limit(trim($result->errorOutput()), 300));
            }

            $pdf = is_file($output) ? (string) file_get_contents($output) : '';

            if (! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('Chrome ran but wrote no PDF.');
            }

            return $pdf;
        } finally {
            File::deleteDirectory($folder);
        }
    }

    /** A file:// address Chrome accepts on Linux and on Windows. */
    private static function fileUrl(string $path): string
    {
        return 'file:///'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
