<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets the roles a user holds (EUISIS AssignRolesAction; a user may hold
 * several). Refused when it would take the administrator role from yourself
 * or from the last active administrator, and when it would mix the applicant
 * role with staff roles: applicants belong to an organization, staff do not.
 */
final class SyncUserRoles
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  list<string>  $roles  role names
     */
    public function handle(User $user, array $roles, User $actor): User
    {
        $roles = array_values(array_unique($roles));
        $old = $user->getRoleNames()->sort()->values()->all();
        $admin = PermissionCatalog::PROTECTED_ROLE;
        $losesAdmin = in_array($admin, $old, true) && ! in_array($admin, $roles, true);

        if ($losesAdmin && $actor->is($user)) {
            throw ValidationException::withMessages(['roles' => __('rbac.users.errors.own_admin')]);
        }

        if ($losesAdmin && $user->isActive() && User::role($admin)->whereKeyNot($user->getKey())->whereNull('deactivated_at')->doesntExist()) {
            throw ValidationException::withMessages(['roles' => __('rbac.users.errors.last_admin')]);
        }

        if (in_array(RoleEnum::Applicant->value, $roles, true) && count($roles) > 1) {
            throw ValidationException::withMessages(['roles' => __('rbac.users.errors.applicant_mixed')]);
        }

        DB::transaction(function () use ($user, $roles, $old, $actor): void {
            $user->syncRoles($roles);
            $new = collect($roles)->sort()->values()->all();

            if ($new !== $old) {
                $this->audit->record(AuditEvent::UserRoleChanged, $user, $actor, old: ['roles' => $old], new: ['roles' => $new]);
            }
        });

        $this->registrar->forgetCachedPermissions();

        return $user;
    }
}
