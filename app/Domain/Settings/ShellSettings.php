<?php

namespace App\Domain\Settings;

/**
 * The settings the signed-in shell (resources/js/shell)
 * reads, flat and under the keys the source kit uses ('appearance.primary_color', …),
 * so the shell's code could stay as it was. Only presentation settings: none
 * of these is a secret or decides access.
 *
 * The brand colour is the custom colour when one is set, otherwise the
 * colour of the chosen preset; the shell derives the hover, dark-theme and
 * sidebar variants from it (resources/js/shell/lib/brandColor.ts).
 */
final class ShellSettings
{
    /** The brand colour of each colour preset; mirrors resources/css/app.css. */
    private const array PRESET_PRIMARY = [
        'navy' => '#122170',
        'forest' => '#166534',
        'sunset' => '#b4410f',
        'graphite' => '#27272a',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        $appearance = (array) config('permits.appearance');
        $theme = (string) ($appearance['theme'] ?? 'navy');
        $environment = (string) app()->environment();

        return [
            'appearance.primary_color' => self::color($appearance['primary_color'] ?? null)
                ?? self::PRESET_PRIMARY[$theme] ?? self::PRESET_PRIMARY['navy'],
            'appearance.secondary_color' => '#1d3084',
            'appearance.accent_color' => self::color($appearance['accent_color'] ?? null) ?? '#d12908',
            'appearance.sidebar_color' => self::color($appearance['sidebar_color'] ?? null) ?? '#ffffff',
            'appearance.ui_style' => in_array($appearance['ui_style'] ?? null, SettingsRegistry::UI_STYLES, true)
                ? $appearance['ui_style'] : 'swiss',
            'appearance.button_style' => (string) ($appearance['button_style'] ?? 'auto'),
            'appearance.card_radius' => (string) ($appearance['card_radius'] ?? 'auto'),
            'appearance.table_density' => (string) ($appearance['table_density'] ?? 'comfortable'),
            'appearance.allow_user_theme_switching' => (bool) ($appearance['allow_user_mode'] ?? true),
            'appearance.sidebar_compact_default' => (bool) ($appearance['sidebar_compact'] ?? false),
            'appearance.show_breadcrumbs' => (bool) ($appearance['show_breadcrumbs'] ?? true),
            'appearance.show_language_switcher' => (bool) ($appearance['show_language_switcher'] ?? true),
            'appearance.enable_ui_animations' => (bool) ($appearance['animations'] ?? true),
            // Shown in the sidebar outside production, so test data is never mistaken for live.
            'general.system_environment_label' => $environment === 'production' ? '' : $environment,
        ];
    }

    private static function color(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : null;
    }
}
