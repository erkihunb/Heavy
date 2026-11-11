<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * The subset of language lines that is shipped to the React frontend.
 *
 * Only the namespaces in `localization.frontend_namespaces` are sent. The
 * fallback locale defines the set of keys (and the generated TypeScript key
 * type); a line missing from another locale falls back to it.
 */
final class FrontendTranslations
{
    /**
     * @return array<string, array<string, mixed>> namespace => nested lines
     */
    public static function for(string $locale): array
    {
        $fallback = (string) config('app.fallback_locale');
        $translations = [];

        foreach (self::namespaces() as $namespace) {
            $translations[$namespace] = array_replace_recursive(
                self::load($namespace, $fallback),
                self::load($namespace, $locale),
            );
        }

        return $translations;
    }

    /**
     * Every dot-notation key available to the frontend, e.g. "common.language".
     *
     * @return list<string>
     */
    public static function keys(?string $locale = null): array
    {
        $locale ??= (string) config('app.fallback_locale');
        $lines = [];

        foreach (self::namespaces() as $namespace) {
            $lines[$namespace] = self::load($namespace, $locale);
        }

        $keys = array_keys(Arr::dot($lines));
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function namespaces(): array
    {
        return array_values((array) config('localization.frontend_namespaces'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(string $namespace, string $locale): array
    {
        $path = lang_path("{$locale}/{$namespace}.php");

        if (! is_file($path)) {
            return [];
        }

        $lines = require $path;

        return is_array($lines) ? $lines : [];
    }
}
