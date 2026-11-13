<?php

namespace App\Concerns;

use App\Actions\Users\CreateStaffUser;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, Rule|Closure|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
            // The default password new accounts start with is known to the whole office.
            $default = CreateStaffUser::defaultPassword();

            if ($default !== null && is_string($value) && hash_equals($default, $value)) {
                $fail(__('auth.default_password_refused'));
            }
        }];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
