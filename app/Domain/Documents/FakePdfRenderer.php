<?php

namespace App\Domain\Documents;

/**
 * A stand-in renderer for tests and for development machines without
 * Gotenberg (`PERMITS_PDF_RENDERER=fake`). It returns a tiny valid PDF and
 * remembers the HTML it was given, so tests can check what would be printed.
 */
final class FakePdfRenderer implements PdfRenderer
{
    /** @var list<string> */
    public static array $rendered = [];

    public function render(string $html): string
    {
        self::$rendered[] = $html;

        return "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
    }

    public static function reset(): void
    {
        self::$rendered = [];
    }
}
