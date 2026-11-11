<?php

namespace App\Domain\Settings;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The settings an Admin changes without a deployment (SettingsRegistry).
 * Defaults come from config/*.php and .env; a value saved here overrides
 * its config key, so the rest of the application keeps reading config().
 * Secrets (passwords, tokens) are stored encrypted with the application key.
 *
 * Saved values are cached and applied when the application boots and again
 * before every queued job, so long-running queue workers see a change too.
 */
final class SystemSettings
{
    public const string CACHE_KEY = 'system_settings.v2';

    public const string MASK = '••••••';

    public function __construct(private readonly AuditLog $audit) {}

    /**
     * Saved values, from the cache. Secrets are still encrypted here.
     *
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        /** @var array<string, mixed> */
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => DB::table('settings')
            ->whereIn('key', array_keys(SettingsRegistry::all()))
            ->pluck('value', 'key')
            ->map(fn (string $json): mixed => json_decode($json, true))
            ->all());
    }

    /**
     * Put the saved values over the config defaults. Called while booting:
     * when the database or the table is not there yet (a fresh install, a
     * Docker build), the defaults stay.
     */
    public function apply(): void
    {
        try {
            $stored = $this->stored();
        } catch (Throwable) {
            return;
        }

        $registry = SettingsRegistry::all();

        foreach ($stored as $key => $value) {
            if (($registry[$key]['type'] ?? null) === 'secret' && is_string($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    // Saved under another APP_KEY: unusable, so the default stays.
                    continue;
                }
            }

            config([$key => $value]);
        }

        // A mailer built before the settings changed keeps its old transport.
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    /**
     * The values in force for a section. Secrets are never returned; their
     * key maps to whether one is set.
     *
     * @return array<string, mixed>
     */
    public function current(string $section): array
    {
        $values = [];

        foreach (SettingsRegistry::section($section) as $key => $setting) {
            $value = config($key);

            $values[$key] = match ($setting['type']) {
                'secret' => filled($value),
                'bool' => (bool) $value,
                'int' => (int) $value,
                'choice' => in_array($value, $setting['options'] ?? [], true) ? $value : ($setting['options'][0] ?? null),
                default => $value === null ? '' : (string) $value,
            };
        }

        return $values;
    }

    /**
     * The keys of a section whose value was saved here.
     *
     * @return list<string>
     */
    public function savedKeys(string $section): array
    {
        try {
            return array_values(array_intersect(array_keys($this->stored()), array_keys(SettingsRegistry::section($section))));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Save the given values of one section; only those that differ from the
     * current ones are written and audited (secrets as "changed", never their value).
     *
     * @param  array<string, mixed>  $values  validated, keyed by setting key; a secret left empty keeps its value
     * @param  list<string>  $clear  secret keys to empty
     * @return list<string> the keys that changed
     */
    public function update(string $section, array $values, array $clear, User $actor): array
    {
        $settings = SettingsRegistry::section($section);
        $current = $this->current($section);
        $writes = [];
        $old = [];
        $new = [];

        foreach ($settings as $key => $setting) {
            if ($setting['type'] === 'secret') {
                if (in_array($key, $clear, true) && $current[$key] === true) {
                    $writes[$key] = null;
                    $old[$key] = self::MASK;
                    $new[$key] = null;
                } elseif (is_string($values[$key] ?? null) && $values[$key] !== '' && $values[$key] !== config($key)) {
                    $writes[$key] = Crypt::encryptString($values[$key]);
                    $old[$key] = $current[$key] === true ? self::MASK : null;
                    $new[$key] = self::MASK;
                }

                continue;
            }

            // Uploaded files are saved by updateAssets().
            if ($setting['type'] === 'image' || ! array_key_exists($key, $values)) {
                continue;
            }

            $value = $this->normalize($setting['type'], $values[$key]);

            // A choice is compared with the value really in force: the form shows the
            // first option when the server is configured with one not offered here.
            $before = $setting['type'] === 'choice' ? config($key) : $current[$key];

            if ($value !== $this->normalize($setting['type'], $before)) {
                $writes[$key] = $value;
                $old[$key] = $before;
                $new[$key] = $value;
            }
        }

        if ($writes === []) {
            return [];
        }

        DB::transaction(function () use ($writes, $old, $new, $section, $actor): void {
            $now = now();

            foreach ($writes as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_by' => $actor->id, 'updated_at' => $now],
                );
            }

            $this->audit->record(AuditEvent::SettingsUpdated, actor: $actor, old: $old, new: $new, context: ['section' => $section]);
        });

        Cache::forget(self::CACHE_KEY);
        $this->apply();

        return array_keys($writes);
    }

    /**
     * Replace or remove the uploaded files of a section (logo, favicon). The
     * old file is deleted once the new path is saved; the audit log notes
     * that the file changed, not its content.
     *
     * @param  array<string, UploadedFile>  $files  keyed by setting key
     * @param  list<string>  $remove  setting keys whose file is taken down
     * @return list<string> the keys that changed
     */
    public function updateAssets(string $section, array $files, array $remove, User $actor): array
    {
        $writes = [];
        $previous = [];
        $old = [];
        $new = [];

        foreach (SettingsRegistry::section($section) as $key => $setting) {
            if ($setting['type'] !== 'image') {
                continue;
            }

            $asset = Str::afterLast($key, '.');
            $before = Branding::path($asset);

            if (isset($files[$key])) {
                $writes[$key] = Branding::store($asset, $files[$key]);
            } elseif (in_array($key, $remove, true) && $before !== null) {
                $writes[$key] = null;
            } else {
                continue;
            }

            $previous[$key] = $before;
            $old[$key] = $before === null ? null : 'file';
            $new[$key] = $writes[$key] === null ? null : 'file';
        }

        if ($writes === []) {
            return [];
        }

        try {
            DB::transaction(function () use ($writes, $old, $new, $section, $actor): void {
                foreach ($writes as $key => $value) {
                    DB::table('settings')->updateOrInsert(
                        ['key' => $key],
                        ['value' => json_encode($value), 'updated_by' => $actor->id, 'updated_at' => now()],
                    );
                }

                $this->audit->record(AuditEvent::SettingsUpdated, actor: $actor, old: $old, new: $new, context: ['section' => $section]);
            });
        } catch (Throwable $exception) {
            // Nothing was saved: the files just written are not needed.
            foreach ($writes as $path) {
                Branding::delete($path);
            }

            throw $exception;
        }

        foreach ($previous as $path) {
            Branding::delete($path);
        }

        Cache::forget(self::CACHE_KEY);
        $this->apply();

        return array_keys($writes);
    }

    private function normalize(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'text', 'longtext', 'email', 'url', 'color' => is_string($value) && trim($value) !== '' ? trim($value) : null,
            default => is_string($value) ? trim($value) : $value,
        };
    }
}
