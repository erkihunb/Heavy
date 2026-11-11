<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeUserRole
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * Give a member of staff another role, or none (access removed: the
     * account stays, for the history it is part of). The policy has already
     * checked that the Admin is not changing their own role.
     */
    public function handle(User $user, ?Role $role, User $admin): void
    {
        if ($role !== null && ! $role->isStaff()) {
            throw new InvalidArgumentException('Only staff roles are given here.');
        }

        DB::transaction(function () use ($user, $role, $admin): void {
            $old = $user->getRoleNames()->values()->all();
            $new = $role === null ? [] : [$role->value];

            if ($old === $new) {
                return;
            }

            $user->syncRoles($new);

            $this->audit->record(AuditEvent::UserRoleChanged, $user, $admin, old: ['roles' => $old], new: ['roles' => $new]);
        });
    }
}
