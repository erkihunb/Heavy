<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives a deactivated user their access back (EUISIS RestoreUserAction),
 * with the roles and branch scopes they had.
 */
final class RestoreUser
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(User $user, User $actor): User
    {
        if ($user->isActive()) {
            return $user;
        }

        return DB::transaction(function () use ($user, $actor): User {
            $user->forceFill(['deactivated_at' => null, 'deactivated_by' => null])->save();
            $this->audit->record(AuditEvent::UserRestored, $user, $actor, old: ['status' => 'inactive'], new: ['status' => 'active']);

            return $user;
        });
    }
}
