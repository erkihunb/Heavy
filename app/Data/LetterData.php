<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * The letter a request came in with, as the Registrar records it at the office.
 */
final readonly class LetterData
{
    public function __construct(
        /** The letter's reference number. */
        public string $number,
        public CarbonImmutable $date,
        /** The scanned letter; null when an edit keeps the one already stored. */
        public ?UploadedFile $file = null,
    ) {}
}
