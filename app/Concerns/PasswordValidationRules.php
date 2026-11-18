<?php

namespace App\Concerns;

use App\Models\User;
use App\Security\PasswordPolicy;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @param  User|null  $user  whose password it becomes, when known
     * @param  list<string|null>  $personal  the details of an account not saved yet
     * @return array<int, Rule|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(?User $user = null, array $personal = []): array
    {
        return ['required', 'string', Password::default(), 'confirmed', new PasswordPolicy($user, $personal)];
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
