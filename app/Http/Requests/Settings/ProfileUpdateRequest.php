<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Admin\Concerns\ValidatesStaffProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * What a person may change about themself: their photo, name, username, national ID, gender,
 * phone, e-mail and preferences. Where they sit in the institution, their branch and their
 * roles stay with whoever manages users, so those fields are neither validated nor stored here.
 */
class ProfileUpdateRequest extends FormRequest
{
    use ValidatesStaffProfile;

    /** The stored columns a person may change on their own account. */
    public const array OWN_COLUMNS = [
        'first_name', 'middle_name', 'last_name', 'username', 'national_id', 'gender', 'phone',
        'locale', 'timezone', 'theme', 'notification_preferences',
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            ...Arr::only($this->profileRules($user), [
                'name', 'first_name', 'middle_name', 'last_name', 'username', 'national_id', 'gender', 'phone',
                'locale', 'timezone', 'theme', 'notify_email', 'avatar',
            ]),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'remove_avatar' => ['boolean'],
        ];
    }

    /**
     * The person's own columns as they are stored; nothing about their employment.
     *
     * @return array<string, mixed>
     */
    public function ownProfile(): array
    {
        return Arr::only($this->profile(), self::OWN_COLUMNS);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->profileAttributes();
    }
}
