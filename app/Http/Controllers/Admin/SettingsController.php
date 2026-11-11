<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Branding;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Settings\SystemSettings;
use App\Domain\Sms\SmsGateway;
use App\Domain\Telegram\TelegramGateway;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use App\Support\EthiopianPhone;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * System settings (spec §11): an overview of the system's health and
 * configuration, and one page per section of settings an Admin can change.
 */
class SettingsController extends Controller
{
    public function overview(SystemSettings $settings): Response
    {
        Gate::authorize('manageSettings');

        $production = app()->isProduction();
        $lastChange = Activity::query()->where('event', 'settings.updated')->with('causer')->latest('id')->first();
        $timezone = (string) config('localization.display_timezone');

        return Inertia::render('admin/settings/overview', [
            'system' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'php' => PHP_VERSION,
                'laravel' => Application::VERSION,
                'locale' => config('app.locale'),
                'timezone' => $timezone,
            ],
            'services' => [
                'database' => $this->databaseStatus(),
                'cache' => (string) config('cache.default'),
                'queue' => (string) config('queue.default'),
                'queued_jobs' => $this->tableCount('jobs'),
                'failed_jobs' => $this->tableCount('failed_jobs'),
                'mail' => (string) config('mail.default'),
                'sms' => (string) config('services.sms.driver'),
                'telegram' => (bool) config('services.telegram.enabled') && app(TelegramGateway::class)->configured(),
                'pdf' => (string) config('permits.pdf_renderer'),
                'error_tracking' => filled(config('sentry.dsn')),
                'two_factor' => (bool) config('permits.staff_two_factor_required'),
            ],
            'counts' => [
                'users' => User::query()->count(),
                'staff' => User::role(Role::staffValues())->count(),
                'organizations' => Organization::query()->count(),
                'vehicles' => Vehicle::query()->count(),
                'permits_in_force' => Permit::query()->where('status', PermitStatus::Issued)->count(),
                'payments_waiting' => Payment::query()->where('status', PaymentStatus::Submitted)->count(),
            ],
            'warnings' => $this->warnings($production),
            'lastChange' => $lastChange === null ? null : [
                'by' => $lastChange->causer instanceof User ? $lastChange->causer->name : null,
                'at' => EthiopianCalendar::present($lastChange->created_at?->setTimezone($timezone), app()->getLocale()),
                'time' => $lastChange->created_at?->setTimezone($timezone)->format('H:i'),
            ],
            'sections' => SettingsRegistry::SECTIONS,
        ]);
    }

    public function show(SystemSettings $settings, string $section): Response
    {
        Gate::authorize('manageSettings');

        $current = $settings->current($section);

        return Inertia::render('admin/settings/section', [
            'section' => $section,
            'sections' => SettingsRegistry::SECTIONS,
            'fields' => array_map(
                fn (string $key, array $setting): array => array_filter([
                    'key' => $key,
                    'field' => SettingsRegistry::field($key),
                    'group' => $setting['group'],
                    'type' => $setting['type'],
                    'required' => $setting['required'] ?? false,
                    'min' => $setting['min'] ?? null,
                    'max' => $setting['max'] ?? null,
                    'options' => $setting['options'] ?? null,
                    'kind' => $setting['kind'] ?? null,
                    'value' => in_array($setting['type'], ['secret', 'image'], true) ? null : $current[$key],
                    'has_value' => match ($setting['type']) {
                        'secret' => $current[$key],
                        'image' => Branding::path(Str::afterLast($key, '.')) !== null,
                        default => null,
                    },
                    'url' => $setting['type'] === 'image' ? Branding::url(Str::afterLast($key, '.')) : null,
                ], fn (mixed $value): bool => $value !== null),
                array_keys(SettingsRegistry::section($section)),
                SettingsRegistry::section($section),
            ),
            'saved' => array_map(SettingsRegistry::field(...), $settings->savedKeys($section)),
            'timezones' => $section === 'localization' ? ['Africa/Addis_Ababa', 'UTC'] : [],
        ]);
    }

    public function update(Request $request, SystemSettings $settings, string $section): RedirectResponse
    {
        Gate::authorize('manageSettings');

        $definitions = SettingsRegistry::section($section);
        $input = [];
        $rules = [];
        $attributes = [];

        foreach ($definitions as $key => $setting) {
            $field = SettingsRegistry::field($key);
            $input[$field] = match ($setting['type']) {
                'bool' => $request->boolean($field),
                'image' => $request->file($field),
                default => $request->input($field),
            };
            $rules[$field] = SettingsRegistry::rules($setting);
            $attributes[$field] = __("admin.settings.fields.{$field}");
        }

        $validated = Validator::make($input, $rules, [], $attributes)->validate();
        $clear = array_values(array_filter(
            array_keys($definitions),
            fn (string $key): bool => $definitions[$key]['type'] === 'secret' && $request->boolean(SettingsRegistry::field($key).'__clear'),
        ));

        $values = [];

        foreach (array_keys($definitions) as $key) {
            $values[$key] = $validated[SettingsRegistry::field($key)] ?? null;
        }

        $this->ensureUsable($section, $values, $clear);

        $images = array_keys(array_filter($definitions, fn (array $setting): bool => $setting['type'] === 'image'));
        $files = array_filter(
            array_combine($images, array_map(fn (string $key): mixed => $values[$key], $images)),
            fn (mixed $file): bool => $file instanceof UploadedFile,
        );
        $remove = array_values(array_filter(
            $images,
            fn (string $key): bool => in_array(SettingsRegistry::field($key), (array) $request->input('remove_assets', []), true),
        ));

        $changed = [
            ...$settings->update($section, $values, $clear, $request->user()),
            ...$settings->updateAssets($section, $files, $remove, $request->user()),
        ];

        return to_route('admin.settings.show', $section)->with(
            'success',
            $changed === [] ? __('admin.settings.messages.unchanged') : __('admin.settings.messages.saved'),
        );
    }

    /**
     * Send a test e-mail, SMS or Telegram message with the settings in force.
     */
    public function test(Request $request, string $section): RedirectResponse
    {
        Gate::authorize('manageSettings');

        try {
            match ($section) {
                'email' => Mail::raw(__('admin.settings.test.email_body'), function ($message) use ($request): void {
                    $message->to($request->user()->email)->subject(__('admin.settings.test.email_subject', ['app' => config('app.name')]));
                }),
                'sms' => app(SmsGateway::class)->send($this->testPhone($request), __('admin.settings.test.sms_body', ['app' => config('app.name')])),
                'telegram' => app(TelegramGateway::class)->send(__('admin.settings.test.telegram_body', ['app' => config('app.name')])),
                default => abort(404),
            };
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('warning', __('admin.settings.test.failed', ['error' => mb_substr($exception->getMessage(), 0, 200)]));
        }

        return back()->with('success', __("admin.settings.test.sent_{$section}"));
    }

    private function testPhone(Request $request): string
    {
        $phone = EthiopianPhone::normalize((string) $request->input('phone'));

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('admin.settings.test.phone_invalid')]);
        }

        return $phone;
    }

    /**
     * Refuse settings that would switch a channel on without what it needs.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $clear
     */
    private function ensureUsable(string $section, array $values, array $clear): void
    {
        $has = fn (string $key): bool => ! in_array($key, $clear, true) && (filled($values[$key] ?? null) || filled(config($key)));
        $errors = [];

        if ($section === 'email' && $values['mail.default'] === 'smtp' && blank($values['mail.mailers.smtp.host'])) {
            $errors[SettingsRegistry::field('mail.mailers.smtp.host')] = __('admin.settings.errors.smtp_host');
        }

        if ($section === 'sms' && $values['services.sms.driver'] === 'http' && blank($values['services.sms.http.url'])) {
            $errors[SettingsRegistry::field('services.sms.http.url')] = __('admin.settings.errors.sms_url');
        }

        if ($section === 'telegram' && $values['services.telegram.enabled'] === true) {
            if (! $has('services.telegram.bot_token')) {
                $errors[SettingsRegistry::field('services.telegram.bot_token')] = __('admin.settings.errors.telegram_token');
            }

            if (blank($values['services.telegram.chat_id'])) {
                $errors[SettingsRegistry::field('services.telegram.chat_id')] = __('admin.settings.errors.telegram_chat');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return list<string> translation keys under admin.settings.warnings
     */
    private function warnings(bool $production): array
    {
        $warnings = [];

        foreach (['full_day', 'pass'] as $type) {
            foreach (['am', 'en'] as $locale) {
                $text = (string) config("permits.time_windows.{$type}.{$locale}");

                if (str_contains($text, '[PLACEHOLDER]') || str_contains($text, '[ጊዜያዊ ጽሑፍ]')) {
                    $warnings[] = 'placeholder_time_windows';
                    break 2;
                }
            }
        }

        if (! config('permits.staff_two_factor_required')) {
            $warnings[] = 'two_factor_off';
        }

        if ($production) {
            if (config('app.debug')) {
                $warnings[] = 'debug_on';
            }

            if (config('mail.default') === 'log') {
                $warnings[] = 'mail_log';
            }

            if (config('services.sms.driver') === 'log' && config('permits.notifications.sms')) {
                $warnings[] = 'sms_log';
            }

            if (config('permits.pdf_renderer') === 'fake') {
                $warnings[] = 'pdf_fake';
            }

            if (blank(config('sentry.dsn'))) {
                $warnings[] = 'no_error_tracking';
            }
        }

        if ($this->tableCount('failed_jobs') > 0) {
            $warnings[] = 'failed_jobs';
        }

        return $warnings;
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('SELECT 1');

            return (string) config('database.default');
        } catch (Throwable) {
            return 'down';
        }
    }

    private function tableCount(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }
}
