<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\User;
use App\Support\EthiopianPhone;
use App\Support\Rbac\PermissionCatalog;
use App\Support\UserAvatar;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The staff record an Admin keeps besides the e-mail and roles: the name in
 * its three parts, username, national ID, gender, phone and photo; where the
 * person sits in the institution; their preferences; and permissions given
 * to them directly, on top of their roles.
 */
trait ValidatesStaffProfile
{
    public const array GENDERS = ['male', 'female'];

    public const array EMPLOYMENT_STATUSES = ['permanent', 'contract', 'temporary', 'intern'];

    public const array THEMES = ['system', 'light', 'dark'];

    /**
     * @return array<string, array<mixed>>
     */
    protected function profileRules(?User $ignore = null): array
    {
        return [
            // The whole name, or its parts: forms send the parts, older callers the whole.
            // A form that has the parts is told which part is missing, not that "the name" is.
            'name' => [Rule::requiredIf(fn (): bool => ! $this->has('first_name')), 'nullable', 'string', 'max:255'],
            'first_name' => [Rule::requiredIf(fn (): bool => ! $this->filled('name')), 'nullable', 'string', 'max:100'],
            'middle_name' => [Rule::requiredIf(fn (): bool => ! $this->filled('name')), 'nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[a-z][a-z0-9._-]*$/i', Rule::unique(User::class, 'username')->ignore($ignore?->id)],
            'national_id' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
                $id = self::nationalIdDigits($value);

                if ($id === null) {
                    return;
                }

                if (preg_match('/^(\d{12}|\d{16})$/', $id) !== 1) {
                    $fail(__('rbac.users.errors.national_id_invalid'));

                    return;
                }

                if (User::query()->where('national_id', $id)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->exists()) {
                    $fail(__('rbac.users.errors.national_id_taken'));
                }
            }],
            'gender' => ['nullable', 'string', Rule::in(self::GENDERS)],
            'phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && EthiopianPhone::normalize($value) === null) {
                    $fail(__('organization.errors.phone_invalid'));
                }
            }],
            'institution' => ['nullable', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'employee_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\/\-\.]+$/', Rule::unique(User::class, 'employee_number')->ignore($ignore?->id)],
            'manager_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->whereNull('deactivated_at'), function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
                if ($ignore !== null && (int) $value === $ignore->id) {
                    $fail(__('rbac.users.errors.manager_self'));
                }
            }],
            'employment_status' => ['nullable', 'string', Rule::in(self::EMPLOYMENT_STATUSES)],
            'locale' => ['nullable', 'string', Rule::in(array_keys((array) config('localization.supported')))],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'theme' => ['nullable', 'string', Rule::in(self::THEMES)],
            'notify_email' => ['sometimes', 'boolean'],
            'avatar' => UserAvatar::rules(),
        ];
    }

    /**
     * Permissions given to the person directly: only the office's work, and
     * only what the Admin holds themself, so nobody hands out more than they have.
     *
     * @return array<string, array<mixed>>
     */
    protected function directPermissionRules(): array
    {
        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalog::staffPermissions()), function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! ($this->user()?->can($value) ?? false)) {
                    $fail(__('rbac.users.errors.permission_not_held'));
                }
            }],
        ];
    }

    /**
     * @return list<string>
     */
    public function directPermissions(): array
    {
        return array_values(array_unique(array_filter((array) $this->input('permissions', []), 'is_string')));
    }

    /**
     * The name everything else reads: the parts joined, or the whole name as sent.
     */
    public function fullName(): string
    {
        return $this->filled('first_name')
            ? implode(' ', array_filter(array_map(fn (string $key): string => trim($this->string($key)->toString()), ['first_name', 'middle_name', 'last_name'])))
            : trim($this->string('name')->toString());
    }

    /**
     * The profile columns as they are stored.
     *
     * The language and the notification choice are left out when not sent,
     * so the stored ones stay.
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->toString()) : null;
        $phone = $text('phone');
        $parts = $this->filled('first_name')
            ? [$text('first_name'), $text('middle_name'), $text('last_name')]
            : (preg_split('/\s+/u', $this->fullName(), 3, PREG_SPLIT_NO_EMPTY) ?: []);

        $profile = [
            'first_name' => $parts[0] ?? null,
            'middle_name' => $parts[1] ?? null,
            'last_name' => $parts[2] ?? null,
            'username' => $this->filled('username') ? Str::lower((string) $text('username')) : null,
            'national_id' => self::nationalIdDigits($this->input('national_id')),
            'gender' => $text('gender'),
            'phone' => $phone === null ? null : EthiopianPhone::normalize($phone),
            'institution' => $text('institution'),
            'department' => $text('department'),
            'job_title' => $text('job_title'),
            'employee_number' => $text('employee_number'),
            'manager_id' => $this->filled('manager_id') ? $this->integer('manager_id') : null,
            'employment_status' => $text('employment_status'),
            'timezone' => $text('timezone'),
            'theme' => $text('theme'),
        ];

        if ($this->filled('locale')) {
            $profile['locale'] = $this->string('locale')->toString();
        }

        if ($this->has('notify_email')) {
            $profile['notification_preferences'] = ['email' => $this->boolean('notify_email')];
        }

        return $profile;
    }

    public function avatar(): ?UploadedFile
    {
        $file = $this->file('avatar');

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * The ID as typed, without the spaces printed on the card.
     */
    private static function nationalIdDigits(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/[\s\-]+/', '', $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * @return array<string, string>
     */
    protected function profileAttributes(): array
    {
        return [
            'name' => __('admin.users.fields.name'),
            'first_name' => __('rbac.users.fields.firstName'),
            'middle_name' => __('rbac.users.fields.middleName'),
            'last_name' => __('rbac.users.fields.lastName'),
            'username' => __('rbac.users.fields.username'),
            'national_id' => __('rbac.users.fields.nationalId'),
            'gender' => __('rbac.users.fields.gender'),
            'phone' => __('rbac.users.fields.phone'),
            'institution' => __('rbac.users.fields.institution'),
            'department' => __('rbac.users.fields.department'),
            'job_title' => __('rbac.users.fields.jobTitle'),
            'employee_number' => __('rbac.users.fields.employeeNumber'),
            'manager_id' => __('rbac.users.fields.manager'),
            'employment_status' => __('rbac.users.fields.employmentStatus'),
            'locale' => __('rbac.users.fields.locale'),
            'timezone' => __('rbac.users.fields.timezone'),
            'theme' => __('rbac.users.fields.theme'),
            'avatar' => __('rbac.users.fields.photo'),
            'permissions' => __('rbac.users.fields.permissions'),
            'permissions.*' => __('rbac.users.fields.permissions'),
        ];
    }
}
