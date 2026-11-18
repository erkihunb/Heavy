<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Gets someone who cannot sign in back in, without anyone learning their
 * own password: a temporary password the person must replace at sign-in, a
 * reset link by e-mail, or turning off a lost two-factor device. Each is
 * audited; the password itself never is.
 */
final class ResetUserSignIn
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly DisableTwoFactorAuthentication $disableTwoFactor,
    ) {}

    /**
     * The person signs in with it and must then choose their own
     * (RequirePasswordChange). Their open sessions end.
     */
    public function setTemporaryPassword(User $user, string $password, User $actor): void
    {
        DB::transaction(function () use ($user, $password, $actor): void {
            // Two saves: User::booted() clears the flag whenever the password changes alone.
            $user->forceFill(['password' => $password, 'remember_token' => null])->save();
            $user->forceFill(['must_change_password' => true])->save();

            $this->endSessions($user);
            $this->audit->record(AuditEvent::UserPasswordReset, $user, $actor, new: ['method' => 'temporary_password']);
        });
    }

    /**
     * The standard reset e-mail, as from "Forgot password".
     */
    public function sendResetLink(User $user, User $actor): void
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['reset' => __('rbac.users.errors.reset_link_failed')]);
        }

        $this->audit->record(AuditEvent::UserPasswordReset, $user, $actor, new: ['method' => 'reset_link']);
    }

    /**
     * For a lost phone: the person sets two-factor up again with a new
     * device at their next sign-in (RequireTwoFactorForStaff).
     */
    public function resetTwoFactor(User $user, User $actor): void
    {
        if ($user->two_factor_secret === null && $user->two_factor_confirmed_at === null) {
            throw ValidationException::withMessages(['two_factor' => __('rbac.users.errors.two_factor_off')]);
        }

        DB::transaction(function () use ($user, $actor): void {
            ($this->disableTwoFactor)($user);
            $this->endSessions($user);
            $this->audit->record(AuditEvent::UserTwoFactorReset, $user, $actor, old: ['two_factor' => 'on'], new: ['two_factor' => 'off']);
        });
    }

    /**
     * An Admin's choice of two-factor for one person, over the policy in
     * System settings › Security: null follows the policy, 'required' always
     * asks for it (RequireTwoFactorForStaff), 'exempt' never does. Two-factor
     * the person already set up stays on; "Turn off two-factor" removes it.
     */
    public function setTwoFactorRequirement(User $user, ?string $requirement, User $actor): void
    {
        if ($user->two_factor_requirement === $requirement) {
            return;
        }

        $old = $user->two_factor_requirement ?? 'policy';

        DB::transaction(function () use ($user, $requirement, $actor, $old): void {
            $user->forceFill(['two_factor_requirement' => $requirement])->save();

            $this->audit->record(
                AuditEvent::UserTwoFactorRequirementChanged,
                $user,
                $actor,
                old: ['two_factor_requirement' => $old],
                new: ['two_factor_requirement' => $requirement ?? 'policy'],
            );
        });
    }

    private function endSessions(User $user): void
    {
        // Other session drivers are covered by "remember me" being cleared and the session expiring.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
