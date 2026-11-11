<?php

namespace App\Domain\Documents;

use RuntimeException;

/**
 * Turns a self-contained HTML document into a PDF. The renderer must be
 * Chromium-based so Ethiopic text is shaped correctly (spec §8).
 */
interface PdfRenderer
{
    /**
     * @param  string  $html  a complete HTML document; fonts and images are embedded
     * @return string the PDF bytes
     *
     * @throws RuntimeException when the document could not be rendered
     */
    public function render(string $html): string;
}
