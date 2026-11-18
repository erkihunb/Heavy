<?php

namespace App\Security;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The password rules an Admin sets in System settings › Security, on top of
 * Password::defaults() (length and, in production, character classes and the
 * breach check): a longest length, no common password, nothing taken from
 * the person's own details, and none of their last few passwords.
 */
final class PasswordPolicy implements ValidationRule
{
    /** Shorter parts of a name or address are too common to refuse. */
    private const int SHORTEST_PERSONAL_PART = 4;

    /**
     * @param  User|null  $user  whose password it becomes, when known (personal details and history)
     * @param  list<string|null>  $personal  more personal details, for an account not saved yet
     */
    public function __construct(
        private readonly ?User $user = null,
        private readonly array $personal = [],
    ) {}

    /**
     * What a password must be, for the checklist under password fields.
     *
     * @return array{min: int, max: int, mixed_case: bool, numbers: bool, symbols: bool, history: int, personal: bool, common: bool, breached: bool}
     */
    public static function summary(): array
    {
        $production = app()->isProduction();

        return [
            'min' => $production ? (int) config('permits.security.password_min_length') : 8,
            'max' => (int) config('permits.security.password_max_length'),
            'mixed_case' => $production,
            'numbers' => $production,
            'symbols' => $production,
            'history' => (int) config('permits.security.password_history_count'),
            'personal' => (bool) config('permits.security.password_block_personal_info'),
            'common' => (bool) config('permits.security.password_block_common'),
            'breached' => $production && (bool) config('permits.security.password_breach_check'),
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $max = (int) config('permits.security.password_max_length');

        if ($max > 0 && mb_strlen($value) > $max) {
            $fail(__('auth.password_policy.too_long', ['max' => $max]));

            return;
        }

        if (config('permits.security.password_block_common') && CommonPasswords::contains($value)) {
            $fail(__('auth.password_policy.common'));
        }

        if (config('permits.security.password_block_personal_info') && $this->containsPersonalDetail($value)) {
            $fail(__('auth.password_policy.personal'));
        }

        $history = (int) config('permits.security.password_history_count');

        if ($history > 0 && $this->user?->exists && $this->usedBefore($value, $history)) {
            $fail(__('auth.password_policy.reused', ['count' => $history]));
        }
    }

    private function containsPersonalDetail(string $password): bool
    {
        $password = mb_strtolower($password);

        foreach ($this->personalParts() as $part) {
            if (str_contains($password, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function personalParts(): array
    {
        $details = $this->personal;

        if ($this->user !== null) {
            $details = [
                ...$details,
                $this->user->name,
                $this->user->first_name,
                $this->user->middle_name,
                $this->user->last_name,
                $this->user->username,
                $this->user->email,
                $this->user->phone,
            ];
        }

        $parts = [];

        foreach ($details as $detail) {
            if (! is_string($detail) || trim($detail) === '') {
                continue;
            }

            $detail = mb_strtolower(trim($detail));

            // An address counts by the part before the @; a phone number by its last digits.
            if (str_contains($detail, '@')) {
                $detail = (string) strstr($detail, '@', true);
            }

            if (preg_match('/^\+?[\d\s-]+$/', $detail) === 1) {
                $digits = (string) preg_replace('/\D/', '', $detail);
                $parts[] = substr($digits, -9);

                continue;
            }

            foreach (preg_split('/[\s._\-]+/u', $detail, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $parts[] = $word;
            }
        }

        return array_values(array_unique(array_filter(
            $parts,
            fn (string $part): bool => mb_strlen($part) >= self::SHORTEST_PERSONAL_PART,
        )));
    }

    /**
     * The current password counts as one of the last ones.
     */
    private function usedBefore(string $password, int $count): bool
    {
        $user = $this->user;

        if ($user === null) {
            return false;
        }

        $hashes = [
            $user->getRawOriginal('password'),
            ...DB::table('password_histories')
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(max(0, $count - 1))
                ->pluck('password')
                ->all(),
        ];

        foreach ($hashes as $hash) {
            if (is_string($hash) && $hash !== '' && Hash::check($password, $hash)) {
                return true;
            }
        }

        return false;
    }
}
