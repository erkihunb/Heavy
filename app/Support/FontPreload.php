<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * The fonts of the first screen, asked for in the page's head.
 *
 * The pages are drawn by scripts, so the browser learns which fonts it needs
 * only once they have run; on a slow phone the words were painted in a
 * fallback face and again about a second later when the fonts arrived. Asked
 * for beside the scripts, they are there when the page is drawn. Only the
 * faces every page uses: Inter for Latin text and figures, and Abyssinica SIL
 * when the page is in Amharic (resources/css/app.css).
 */
final class FontPreload
{
    public const string INTER = 'node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2';

    public const string ABYSSINICA = 'node_modules/@fontsource/abyssinica-sil/files/abyssinica-sil-ethiopic-400-normal.woff2';

    /**
     * The `<link rel="preload">` tags for a page in the given language; none
     * while the Vite dev server serves the fonts, or for a font the build
     * does not list.
     */
    public static function tags(string $locale): string
    {
        if (Vite::isRunningHot()) {
            return '';
        }

        $fonts = str_starts_with($locale, 'am') ? [self::ABYSSINICA, self::INTER] : [self::INTER];
        $tags = [];

        foreach ($fonts as $font) {
            try {
                $url = Vite::asset($font);
            } catch (\Throwable) {
                continue;
            }

            if ($url !== '') {
                // `crossorigin`: fonts are fetched without credentials, and a preload must match.
                $tags[] = sprintf('<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>', e($url));
            }
        }

        return implode('
', $tags);
    }
}
