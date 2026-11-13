<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Someone who gave the right default password is not signed in: the session
 * only remembers, for a few minutes, whose own password is being chosen on
 * the "Choose your own password" page (FirstPasswordController).
 */
final class FirstPasswordChange
{
    private const string KEY = 'first_password';

    private const int MINUTES = 15;

    public static function begin(Request $request, User $user): void
    {
        $request->session()->put(self::KEY, ['id' => $user->id, 'until' => now()->addMinutes(self::MINUTES)->getTimestamp()]);
    }

    /**
     * The person choosing their password, while the session still says so
     * and their account is still on the default password.
     */
    public static function user(Request $request): ?User
    {
        $pending = $request->session()->get(self::KEY);

        if (! is_array($pending) || ! is_int($pending['id'] ?? null) || (int) ($pending['until'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $user = User::query()->find($pending['id']);

        return $user !== null && $user->must_change_password && $user->isActive() ? $user : null;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
