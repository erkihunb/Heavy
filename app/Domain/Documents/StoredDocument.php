<?php

namespace App\Domain\Documents;

use App\Models\Permit;
use Illuminate\Support\Facades\Storage;

/**
 * A permit's stored PDFs, one per language: Amharic in document_path,
 * English in document_path_en.
 *
 * Whether a stored PDF is a real document: a missing file, or the few bytes
 * the test renderer (FakePdfRenderer) writes, is not. Nobody can print it,
 * so it is not handed out and `permits:documents` builds it again. While the
 * fake renderer is configured (tests), its files count as real.
 */
final class StoredDocument
{
    /**
     * The languages a permit is printed in, each with the column holding its PDF.
     */
    public const array LANGUAGES = [
        'am' => 'document_path',
        'en' => 'document_path_en',
    ];

    /**
     * Anything smaller cannot hold the permit page (a real one is tens of kilobytes).
     */
    private const int MIN_BYTES = 1024;

    public static function path(Permit $permit, string $language): ?string
    {
        return $permit->getAttribute(self::LANGUAGES[$language]);
    }

    /**
     * Every stored path of the permit, whichever languages have one.
     *
     * @return list<string>
     */
    public static function paths(Permit $permit): array
    {
        return array_values(array_filter(array_map(
            fn (string $language): ?string => self::path($permit, $language),
            array_keys(self::LANGUAGES),
        )));
    }

    /**
     * Whether the PDF in this language can be handed out; with no language,
     * whether every language's PDF can.
     */
    public static function isUsable(Permit $permit, ?string $language = null): bool
    {
        if ($language === null) {
            foreach (array_keys(self::LANGUAGES) as $each) {
                if (! self::isUsable($permit, $each)) {
                    return false;
                }
            }

            return true;
        }

        $path = self::path($permit, $language);
        $disk = Storage::disk(config('permits.documents_disk'));

        if ($path === null || ! $disk->exists($path)) {
            return false;
        }

        return config('permits.pdf_renderer') === 'fake' || $disk->size($path) >= self::MIN_BYTES;
    }
}
