<?php

namespace App\Domain\Documents;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Renders PDFs with Gotenberg (headless Chromium behind an HTTP API), which
 * runs as its own container. Chosen over Browsershot so the PHP image needs
 * neither Node nor Chrome (docs/DECISIONS.md).
 */
final class GotenbergPdfRenderer implements PdfRenderer
{
    public function __construct(
        private readonly string $url,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function render(string $html): string
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->attach('files', $html, 'index.html', ['Content-Type' => 'text/html; charset=utf-8'])
                ->post(rtrim($this->url, '/').'/forms/chromium/convert/html', [
                    // A4 portrait, margins handled by the page's own CSS.
                    'paperWidth' => '8.27',
                    'paperHeight' => '11.69',
                    'marginTop' => '0',
                    'marginBottom' => '0',
                    'marginLeft' => '0',
                    'marginRight' => '0',
                    'printBackground' => 'true',
                    'preferCssPageSize' => 'true',
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The PDF renderer could not be reached.', 0, $exception);
        }

        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF')) {
            throw new RuntimeException("The PDF renderer answered with HTTP {$response->status()}.");
        }

        return $response->body();
    }
}
