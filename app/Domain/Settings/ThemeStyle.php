<?php

namespace App\Domain\Settings;

/**
 * The theme an Admin chose (System settings → Appearance) as what the page
 * needs: the data attributes for <html> and a small stylesheet with the
 * custom brand, accent and sidebar colours (EUISIS lets the organisation
 * pick them freely). Each colour gets a light- and a dark-theme variant, and
 * text on it is black or white, whichever reads better.
 */
final class ThemeStyle
{
    /**
     * @return array<string, string> data-* attributes for <html>
     */
    public static function attributes(): array
    {
        return [
            'data-theme' => self::choice('theme', SettingsRegistry::THEMES, 'navy'),
            'data-ui-style' => self::choice('ui_style', SettingsRegistry::UI_STYLES, 'swiss'),
            'data-button-style' => self::choice('button_style', ['auto', 'square', 'rounded', 'pill'], 'auto'),
            'data-card-radius' => self::choice('card_radius', ['auto', 'none', 'sm', 'md', 'lg', 'xl'], 'auto'),
            'data-table-density' => self::choice('table_density', ['comfortable', 'compact'], 'comfortable'),
            'data-animations' => config('permits.appearance.animations', true) ? 'on' : 'off',
        ];
    }

    /**
     * The attributes as HTML, escaped.
     */
    public static function attributesHtml(): string
    {
        return implode(' ', array_map(
            fn (string $name, string $value): string => $name.'="'.e($value).'"',
            array_keys(self::attributes()),
            self::attributes(),
        ));
    }

    /**
     * CSS for the custom colours, or an empty string when none is set.
     */
    public static function css(): string
    {
        $primary = self::color('primary_color');
        $accent = self::color('accent_color');
        $sidebar = self::color('sidebar_color');

        $light = [];
        $dark = [];

        if ($primary !== null) {
            $soft = self::luminance($primary) < 0.2 ? "color-mix(in srgb, {$primary} 60%, white)" : $primary;
            $softHex = self::luminance($primary) < 0.2 ? self::mix($primary, '#ffffff', 0.6) : $primary;

            $light += [
                '--primary' => $primary,
                '--primary-hover' => "color-mix(in srgb, {$primary} 85%, ".(self::luminance($primary) < 0.2 ? 'white' : 'black').')',
                '--primary-foreground' => self::readable($primary),
                '--primary-light' => "color-mix(in srgb, {$primary} 14%, white)",
                '--accent' => "color-mix(in srgb, {$primary} 14%, white)",
                '--accent-foreground' => "color-mix(in srgb, {$primary} 80%, black)",
                '--ring' => $primary,
                '--sidebar-primary' => $primary,
                '--sidebar-ring' => $primary,
                '--brand-surface' => $primary,
                '--chart-1' => $primary,
            ];
            $dark += [
                '--primary' => $soft,
                '--primary-hover' => "color-mix(in srgb, {$softHex} 80%, white)",
                '--primary-foreground' => self::readable($softHex),
                '--primary-light' => "color-mix(in srgb, {$primary} 35%, #111112)",
                '--accent' => "color-mix(in srgb, {$primary} 35%, #111112)",
                '--accent-foreground' => '#f4f4f5',
                '--ring' => $soft,
                '--sidebar-primary' => $soft,
                '--sidebar-primary-foreground' => self::readable($softHex),
                '--sidebar-ring' => $soft,
                '--chart-1' => $soft,
            ];
        }

        if ($accent !== null) {
            $light['--brand-accent'] = $accent;
            $dark['--brand-accent'] = "color-mix(in srgb, {$accent} 75%, white)";
        }

        if ($sidebar !== null) {
            $foreground = self::readable($sidebar) === '#ffffff' ? '#f8fafc' : '#1f2937';
            $light += [
                '--sidebar' => $sidebar,
                '--sidebar-foreground' => $foreground,
                '--sidebar-border' => "color-mix(in srgb, {$foreground} 14%, {$sidebar})",
                '--sidebar-accent' => "color-mix(in srgb, {$foreground} 10%, {$sidebar})",
                '--sidebar-accent-foreground' => $foreground,
            ];

            // A light sidebar is not carried into dark mode: the dark surface stays.
            if (self::luminance($sidebar) < 0.4) {
                $dark += [
                    '--sidebar' => $sidebar,
                    '--sidebar-foreground' => $foreground,
                    '--sidebar-border' => "color-mix(in srgb, {$foreground} 14%, {$sidebar})",
                    '--sidebar-accent' => "color-mix(in srgb, {$foreground} 10%, {$sidebar})",
                    '--sidebar-accent-foreground' => $foreground,
                ];
            }

            // When the sidebar is the brand colour, its selected item uses the text colour.
            if ($primary !== null && strcasecmp($primary, $sidebar) === 0) {
                $light['--sidebar-primary'] = $foreground;
            }
        }

        // Dark values are only ever set together with light ones.
        if ($light === []) {
            return '';
        }

        // Specific enough to win over the colour presets in app.css.
        return self::block(':root:root', $light).self::block(':root:root.dark', $dark);
    }

    /**
     * @param  list<string>  $options
     */
    private static function choice(string $key, array $options, string $default): string
    {
        $value = config("permits.appearance.{$key}");

        return in_array($value, $options, true) ? $value : $default;
    }

    private static function color(string $key): ?string
    {
        $value = config("permits.appearance.{$key}");

        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : null;
    }

    /**
     * @param  array<string, string>  $properties
     */
    private static function block(string $selector, array $properties): string
    {
        if ($properties === []) {
            return '';
        }

        $body = implode('', array_map(
            fn (string $name, string $value): string => "{$name}:{$value};",
            array_keys($properties),
            $properties,
        ));

        return "{$selector}{{$body}}";
    }

    /**
     * @return array{float, float, float} 0–1 per channel
     */
    private static function rgb(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
    }

    /** WCAG relative luminance. */
    public static function luminance(string $hex): float
    {
        $channels = array_map(
            fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            self::rgb($hex),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** White or near-black text, whichever contrasts more with the colour. */
    public static function readable(string $hex): string
    {
        $l = self::luminance($hex);

        return (1.05 / ($l + 0.05)) >= (($l + 0.05) / 0.0587) ? '#ffffff' : '#141414';
    }

    private static function mix(string $hex, string $with, float $amount): string
    {
        [$r1, $g1, $b1] = self::rgb($hex);
        [$r2, $g2, $b2] = self::rgb($with);
        $mix = fn (float $a, float $b): string => str_pad(dechex((int) round(($a * (1 - $amount) + $b * $amount) * 255)), 2, '0', STR_PAD_LEFT);

        return '#'.$mix($r1, $r2).$mix($g1, $g2).$mix($b1, $b2);
    }
}
