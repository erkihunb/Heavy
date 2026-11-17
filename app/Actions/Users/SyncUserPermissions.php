<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets the permissions a member of staff holds directly, on top of what
 * their roles allow. Only the office's work can be given this way, and only
 * what the actor holds themself: nobody hands out more than they have.
 * Taking one away is always allowed.
 */
final class SyncUserPermissions
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  list<string>  $permissions  permission names
     */
    public function handle(User $user, array $permissions, User $actor): User
    {
        $permissions = array_values(array_unique(array_intersect($permissions, PermissionCatalog::staffPermissions())));
        $old = $user->getDirectPermissions()->pluck('name')->sort()->values()->all();

        foreach (array_diff($permissions, $old) as $added) {
            if (! $actor->can($added)) {
                throw ValidationException::withMessages(['permissions' => __('rbac.users.errors.permission_not_held')]);
            }
        }

        DB::transaction(function () use ($user, $permissions, $old, $actor): void {
            $user->syncPermissions($permissions);
            $new = collect($permissions)->sort()->values()->all();

            if ($new !== $old) {
                $this->audit->record(AuditEvent::UserPermissionsChanged, $user, $actor, old: ['permissions' => $old], new: ['permissions' => $new]);
            }
        });

        $this->registrar->forgetCachedPermissions();

        return $user;
    }
}
