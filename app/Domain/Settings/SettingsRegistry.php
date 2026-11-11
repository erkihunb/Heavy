<?php

namespace App\Domain\Settings;

/**
 * Every setting an Admin can change in the System settings area (spec §11),
 * by section. The key is the config path the value overrides; defaults stay
 * in config/*.php and .env.
 *
 * Types: text (one line), longtext, int (min/max), bool, choice (options),
 * email, url, time (HH:MM), timezone, secret (stored encrypted, never sent
 * back to the browser).
 */
final class SettingsRegistry
{
    public const array SECTIONS = ['general', 'appearance', 'notifications', 'email', 'sms', 'telegram', 'security', 'localization'];

    public const array THEMES = ['forest', 'ocean', 'sunset', 'graphite'];

    /**
     * @return array<string, array{section: string, group: string, type: string, required?: bool, min?: int, max?: int, options?: list<string>, pattern?: string}>
     */
    public static function all(): array
    {
        return [
            // General
            'app.name' => ['section' => 'general', 'group' => 'system', 'type' => 'text', 'required' => true, 'max' => 100],
            'permits.max_duration_days' => ['section' => 'general', 'group' => 'rules', 'type' => 'int', 'min' => 1, 'max' => 366],
            'permits.max_trailers' => ['section' => 'general', 'group' => 'rules', 'type' => 'int', 'min' => 0, 'max' => 5],
            'permits.two_step_approval' => ['section' => 'general', 'group' => 'rules', 'type' => 'bool'],
            'permits.separation_of_duties' => ['section' => 'general', 'group' => 'rules', 'type' => 'bool'],
            'permits.receipt_max_kilobytes' => ['section' => 'general', 'group' => 'rules', 'type' => 'int', 'min' => 512, 'max' => 20480],
            'permits.exports.keep_days' => ['section' => 'general', 'group' => 'rules', 'type' => 'int', 'min' => 1, 'max' => 90],
            'permits.time_windows.full_day.am' => ['section' => 'general', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],
            'permits.time_windows.full_day.en' => ['section' => 'general', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],
            'permits.time_windows.pass.am' => ['section' => 'general', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],
            'permits.time_windows.pass.en' => ['section' => 'general', 'group' => 'time_windows', 'type' => 'longtext', 'required' => true],

            // Appearance and themes
            'permits.appearance.theme' => ['section' => 'appearance', 'group' => 'theme', 'type' => 'choice', 'options' => self::THEMES],
            'permits.appearance.default_mode' => ['section' => 'appearance', 'group' => 'theme', 'type' => 'choice', 'options' => ['system', 'light', 'dark']],

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
            'permits.verification_per_minute' => ['section' => 'security', 'group' => 'verification', 'type' => 'int', 'min' => 5, 'max' => 300],
            'permits.public_shows_organization' => ['section' => 'security', 'group' => 'verification', 'type' => 'bool'],

            // Localization
            'app.locale' => ['section' => 'localization', 'group' => 'language', 'type' => 'choice', 'options' => ['am', 'en']],
            'localization.display_timezone' => ['section' => 'localization', 'group' => 'time', 'type' => 'timezone'],
        ];
    }

    /**
     * @return array<string, array{section: string, group: string, type: string, required?: bool, min?: int, max?: int, options?: list<string>, pattern?: string}>
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
     * @param  array{type: string, required?: bool, min?: int, max?: int, options?: list<string>, pattern?: string}  $setting
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
            'secret' => ['nullable', 'string', 'max:500'],
            default => ['prohibited'],
        };

        if (isset($setting['pattern'])) {
            $rules[] = 'regex:'.$setting['pattern'];
        }

        return $rules;
    }
}
