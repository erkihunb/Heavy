<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a user's access away without deleting anything (EUISIS
 * DeactivateUserAction): the account stays, with its history, and can be
 * restored. A deactivated user cannot sign in, and their open sessions end.
 *
 * Refused for your own account, and for the last active administrator, so
 * the system always keeps someone who can manage it.
 */
final class DeactivateUser
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(User $user, User $actor): User
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['user' => __('rbac.users.errors.deactivate_self')]);
        }

        if ($user->isActive() && $this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages(['user' => __('rbac.users.errors.last_admin')]);
        }

        return DB::transaction(function () use ($user, $actor): User {
            if (! $user->isActive()) {
                return $user;
            }

            $user->forceFill(['deactivated_at' => now(), 'deactivated_by' => $actor->id])->save();

            // Signed out everywhere: "remember me", and the stored sessions when they are in the database.
            // Other session drivers are covered by the active-account check on every request.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $user->forceFill(['remember_token' => null])->save();

            $this->audit->record(AuditEvent::UserDeactivated, $user, $actor, old: ['status' => 'active'], new: ['status' => 'inactive']);

            return $user;
        });
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if (! $user->hasRole(PermissionCatalog::PROTECTED_ROLE)) {
            return false;
        }

        return User::role(PermissionCatalog::PROTECTED_ROLE)
            ->whereKeyNot($user->getKey())
            ->whereNull('deactivated_at')
            ->doesntExist();
    }
}
