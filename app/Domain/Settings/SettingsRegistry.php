<?php

namespace App\Domain\Settings;

/**
 * Every setting an Admin can change in the System settings area (spec §11),
 * by section. The key is the config path the value overrides; defaults stay
 * in config/*.php and .env.
 *
 * Types: text (one line), longtext, int (min/max), bool, choice (options),
 * email, url, time (HH:MM), timezone, secret (stored encrypted, never sent
 * back to the browser), color (#rrggbb), image (an uploaded file, `kind`
 * logo or favicon; see Branding).
 */
final class SettingsRegistry
{
    /**
     * The sections of the System settings menu, by category, in menu order.
     */
    public const array CATEGORIES = [
        'organization' => ['general', 'appearance', 'localization'],
        'permits' => ['permits', 'codes', 'documents'],
        'communication' => ['notifications', 'email', 'sms', 'telegram'],
        'access' => ['security'],
    ];

    public const array SECTIONS = ['general', 'appearance', 'localization', 'permits', 'codes', 'documents', 'notifications', 'email', 'sms', 'telegram', 'security'];

    public const array THEMES = ['navy', 'forest', 'sunset', 'graphite'];

    /** Interface style presets (EUISIS): radius, depth and contrast. */
    public const array UI_STYLES = ['swiss', 'classic', 'soft', 'contrast'];

    /**
     * @return array<string, array{section: string, group: string, type: string, required?: bool, min?: int, max?: int, options?: list<string>, pattern?: string, kind?: string}>
     */
    public static function all(): array
    {
        return [
            // General: the organisation
            'app.name' => ['section' => 'general', 'group' => 'system', 'type' => 'text', 'required' => true, 'max' => 100],
            'permits.max_duration_days' => ['section' => 'permits', 'group' => 'rules', 'type' => 'int', 'min' => 1, 'max' => 366],
            'permits.max_trailers' => ['section' => 'permits', 'group' => 'rules', 'type' => 'int', 'min' => 0, 'max' => 5],
            'permits.two_step_approval' => ['section' => 'permits', 'group' => 'rules', 'type' => 'bool'],
            'permits.separation_of_duties' => ['section' => 'permits', 'group' => 'rules', 'type' => 'bool'],
            'permits.receipt_max_kilobytes' => ['section' => 'permits', 'group' => 'rules', 'type' => 'int', 'min' => 512, 'max' => 20480],
            'permits.exports.keep_days' => ['section' => 'permits', 'group' => 'rules', 'type' => 'int', 'min' => 1, 'max' => 90],
            // The column holds 32 characters. Only the counter can be longer than its token ({seq} → 8 digits),
            // and it may appear once, so a rule of 30 characters gives at most 31.
            'permits.number_prefix' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'text', 'required' => true, 'max' => 8, 'pattern' => '/^[A-Z0-9]+(-[A-Z0-9]+)*$/'],
            'permits.number_format' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'text', 'required' => true, 'max' => 30, 'pattern' => '/^(?=.*\{year\})(?=.*\{seq\})(?!.*\{seq\}.*\{seq\})(\{prefix\}|\{branch\}|\{type\}|\{year\}|\{seq\}|[A-Z0-9-])+$/'],
            'permits.number_digits' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'int', 'min' => 4, 'max' => 8],
            'permits.number_year_format' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'choice', 'options' => ['full', 'short']],
            'permits.number_reset' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'choice', 'options' => ['yearly', 'never']],
            'permits.number_start' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'int', 'min' => 1, 'max' => 999999],
            'permits.number_type_codes.full_day' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'text', 'required' => true, 'max' => 4, 'pattern' => '/^[A-Z0-9]+$/'],
            'permits.number_type_codes.pass' => ['section' => 'codes', 'group' => 'numbering', 'type' => 'text', 'required' => true, 'max' => 4, 'pattern' => '/^[A-Z0-9]+$/'],
            'permits.certificate_code.enabled' => ['section' => 'codes', 'group' => 'certificate_code', 'type' => 'bool'],
            'permits.certificate_code.prefix' => ['section' => 'codes', 'group' => 'certificate_code', 'type' => 'text', 'max' => 8, 'pattern' => '/^[A-Z0-9]*$/'],
            'permits.certificate_code.length' => ['section' => 'codes', 'group' => 'certificate_code', 'type' => 'int', 'min' => 6, 'max' => 16],
            'permits.time_windows.full_day.am' => ['section' => 'permits', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],
            'permits.time_windows.full_day.en' => ['section' => 'permits', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],
            'permits.time_windows.pass.am' => ['section' => 'permits', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],
            'permits.time_windows.pass.en' => ['section' => 'permits', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],
            // The permit document (PDF)
            'permits.document.notes.am' => ['section' => 'documents', 'group' => 'document_notes', 'type' => 'longtext', 'required' => true],
            'permits.document.notes.en' => ['section' => 'documents', 'group' => 'document_notes', 'type' => 'longtext', 'required' => true],
            'permits.verify_url' => ['section' => 'documents', 'group' => 'verification_address', 'type' => 'url', 'required' => true],
            'permits.branding.logo' => ['section' => 'general', 'group' => 'branding', 'type' => 'image', 'kind' => 'logo'],
            'permits.branding.favicon' => ['section' => 'general', 'group' => 'branding', 'type' => 'image', 'kind' => 'favicon'],
            'permits.branding.support_email' => ['section' => 'general', 'group' => 'support', 'type' => 'email'],
            'permits.branding.support_phone' => ['section' => 'general', 'group' => 'support', 'type' => 'text', 'max' => 40, 'pattern' => '/^\+?[0-9 ()-]{3,40}$/'],
            'permits.branding.login_message.am' => ['section' => 'general', 'group' => 'login', 'type' => 'longtext'],
            'permits.branding.login_message.en' => ['section' => 'general', 'group' => 'login', 'type' => 'longtext'],

            // Appearance and themes
            'permits.appearance.theme' => ['section' => 'appearance', 'group' => 'theme', 'type' => 'choice', 'options' => self::THEMES],
            'permits.appearance.primary_color' => ['section' => 'appearance', 'group' => 'colors', 'type' => 'color'],
            'permits.appearance.accent_color' => ['section' => 'appearance', 'group' => 'colors', 'type' => 'color'],
            'permits.appearance.sidebar_color' => ['section' => 'appearance', 'group' => 'colors', 'type' => 'color'],
            'permits.appearance.ui_style' => ['section' => 'appearance', 'group' => 'style', 'type' => 'choice', 'options' => self::UI_STYLES],
            'permits.appearance.button_style' => ['section' => 'appearance', 'group' => 'style', 'type' => 'choice', 'options' => ['auto', 'square', 'rounded', 'pill']],
            'permits.appearance.card_radius' => ['section' => 'appearance', 'group' => 'style', 'type' => 'choice', 'options' => ['auto', 'none', 'sm', 'md', 'lg', 'xl']],
            'permits.appearance.table_density' => ['section' => 'appearance', 'group' => 'style', 'type' => 'choice', 'options' => ['comfortable', 'compact']],
            'permits.appearance.default_mode' => ['section' => 'appearance', 'group' => 'behaviour', 'type' => 'choice', 'options' => ['system', 'light', 'dark']],
            'permits.appearance.allow_user_mode' => ['section' => 'appearance', 'group' => 'behaviour', 'type' => 'bool'],
            'permits.appearance.sidebar_compact' => ['section' => 'appearance', 'group' => 'behaviour', 'type' => 'bool'],
            'permits.appearance.show_breadcrumbs' => ['section' => 'appearance', 'group' => 'behaviour', 'type' => 'bool'],
            'permits.appearance.show_language_switcher' => ['section' => 'appearance', 'group' => 'behaviour', 'type' => 'bool'],
            'permits.appearance.animations' => ['section' => 'appearance', 'group' => 'behaviour', 'type' => 'bool'],

            // Notifications
            'permits.notifications.mail' => ['section' => 'notifications', 'group' => 'channels', 'type' => 'bool'],
            'permits.notifications.sms' => ['section' => 'notifications', 'group' => 'channels', 'type' => 'bool'],
            'permits.notifications.expiring_days' => ['section' => 'notifications', 'group' => 'reminders', 'type' => 'int', 'min' => 1, 'max' => 30],
            'permits.notifications.reminder_time' => ['section' => 'notifications', 'group' => 'reminders', 'type' => 'time'],

            // E-mail
            'mail.default' => ['section' => 'email', 'group' => 'mailer', 'type' => 'choice', 'options' => ['smtp', 'log']],
            'mail.mailers.smtp.host' => ['section' => 'email', 'group' => 'smtp', 'type' => 'text', 'max' => 255],
            'mail.mailers.smtp.port' => ['section' => 'email', 'group' => 'smtp', 'type' => 'int', 'min' => 1, 'max' => 65535],
            'mail.mailers.smtp.scheme' => ['section' => 'email', 'group' => 'smtp', 'type' => 'choice', 'options' => ['smtp', 'smtps']],
            'mail.mailers.smtp.username' => ['section' => 'email', 'group' => 'smtp', 'type' => 'text', 'max' => 255],
            'mail.mailers.smtp.password' => ['section' => 'email', 'group' => 'smtp', 'type' => 'secret'],
            'mail.from.address' => ['section' => 'email', 'group' => 'sender', 'type' => 'email', 'required' => true],
            'mail.from.name' => ['section' => 'email', 'group' => 'sender', 'type' => 'text', 'required' => true, 'max' => 100],

            // SMS
            'services.sms.driver' => ['section' => 'sms', 'group' => 'provider', 'type' => 'choice', 'options' => ['log', 'http']],
            'services.sms.http.url' => ['section' => 'sms', 'group' => 'http', 'type' => 'url'],
            'services.sms.http.token' => ['section' => 'sms', 'group' => 'http', 'type' => 'secret'],
            'services.sms.http.sender' => ['section' => 'sms', 'group' => 'http', 'type' => 'text', 'max' => 11],

            // Telegram
            'services.telegram.enabled' => ['section' => 'telegram', 'group' => 'bot', 'type' => 'bool'],
            'services.telegram.bot_token' => ['section' => 'telegram', 'group' => 'bot', 'type' => 'secret'],
            'services.telegram.chat_id' => ['section' => 'telegram', 'group' => 'bot', 'type' => 'text', 'max' => 64, 'pattern' => '/^(-?\d+|@[A-Za-z0-9_]{5,})$/'],
            'services.telegram.alerts.submitted' => ['section' => 'telegram', 'group' => 'alerts', 'type' => 'bool'],
            'services.telegram.alerts.payment_submitted' => ['section' => 'telegram', 'group' => 'alerts', 'type' => 'bool'],
            'services.telegram.alerts.revoked' => ['section' => 'telegram', 'group' => 'alerts', 'type' => 'bool'],

            // Security
            'permits.security.password_min_length' => ['section' => 'security', 'group' => 'accounts', 'type' => 'int', 'min' => 8, 'max' => 64],
            'permits.security.login_per_minute' => ['section' => 'security', 'group' => 'accounts', 'type' => 'int', 'min' => 3, 'max' => 20],
            'session.lifetime' => ['section' => 'security', 'group' => 'accounts', 'type' => 'int', 'min' => 15, 'max' => 1440],
            'permits.security.default_password' => ['section' => 'security', 'group' => 'accounts', 'type' => 'secret', 'min' => 8],
            'permits.staff_two_factor_required' => ['section' => 'security', 'group' => 'accounts', 'type' => 'bool'],
            'permits.verification_per_minute' => ['section' => 'security', 'group' => 'verification', 'type' => 'int', 'min' => 5, 'max' => 300],
            'permits.public_shows_organization' => ['section' => 'security', 'group' => 'verification', 'type' => 'bool'],

            // Localization
            'app.locale' => ['section' => 'localization', 'group' => 'language', 'type' => 'choice', 'options' => ['am', 'en']],
            'localization.display_timezone' => ['section' => 'localization', 'group' => 'time', 'type' => 'timezone'],
        ];
    }

    /**
     * @return array<string, array{section: string, group: string, type: string, required?: bool, min?: int, max?: int, options?: list<string>, pattern?: string, kind?: string}>
     */
    public static function section(string $section): array
    {
        return array_filter(self::all(), fn (array $setting): bool => $setting['section'] === $section);
    }

    /**
     * The form field of a setting: its key with dots replaced, so that it is flat.
     */
    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * Validation rules for one setting.
     *
     * @param  array{type: string, required?: bool, min?: int, max?: int, options?: list<string>, pattern?: string, kind?: string}  $setting
     * @return list<mixed>
     */
    public static function rules(array $setting): array
    {
        $presence = ($setting['required'] ?? false) ? 'required' : 'nullable';

        $rules = match ($setting['type']) {
            'text' => [$presence, 'string', 'max:'.($setting['max'] ?? 255)],
            'longtext' => [$presence, 'string', 'max:2000'],
            'int' => ['required', 'integer', 'min:'.$setting['min'], 'max:'.$setting['max']],
            'bool' => ['boolean'],
            'choice' => ['required', 'string', 'in:'.implode(',', $setting['options'] ?? [])],
            'email' => [$presence, 'string', 'email:rfc', 'max:255'],
            'url' => [$presence, 'string', 'url:https,http', 'max:255'],
            'time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone:all'],
            'secret' => ['nullable', 'string', 'min:'.($setting['min'] ?? 1), 'max:500'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // SVG is refused: a drawing can carry script. Raster files are re-encoded on upload.
            'image' => ($setting['kind'] ?? 'logo') === 'favicon'
                ? ['nullable', 'file', 'mimes:png,ico', 'max:512']
                : ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000'],
            default => ['prohibited'],
        };

        if (isset($setting['pattern'])) {
            $rules[] = 'regex:'.$setting['pattern'];
        }

        return $rules;
    }
}
