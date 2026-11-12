<?php

namespace App\Support\Rbac;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the database in line with the code's permission catalogue and
 * default roles, without undoing what administrators changed on the Roles
 * screen (EUISIS RbacSynchronizer). Safe to run any number of times:
 *
 * - every catalogue permission exists; none is ever deleted here;
 * - every default role exists, marked `is_system`; a role created now gets
 *   its default permissions;
 * - a permission that is new to the database is granted to the default roles
 *   that list it, so new features reach the right people on deploy;
 * - permissions an administrator added to or removed from an existing role
 *   stay as they are;
 * - the protected administrator role always holds every staff permission
 *   (PermissionCatalog::protectedRolePermissions());
 * - nobody is assigned a role here.
 */
final class RbacSynchronizer
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * @return array{permissions_created: list<string>, roles_created: list<string>}
     */
    public function sync(): array
    {
        $this->registrar->forgetCachedPermissions();

        $result = DB::transaction(function (): array {
            $createdPermissions = [];

            foreach (PermissionCatalog::names() as $name) {
                $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);

                if ($permission->wasRecentlyCreated) {
                    $createdPermissions[] = $name;
                }
            }

            $createdRoles = [];

            foreach (RoleEnum::cases() as $default) {
                /** @var Role $role */
                $role = Role::query()->firstOrCreate(
                    ['name' => $default->value, 'guard_name' => 'web'],
                    ['scope_type' => Role::SCOPE_BRANCH, 'is_system' => true],
                );

                if (! $role->is_system) {
                    $role->forceFill(['is_system' => true])->save();
                }

                $defaults = array_map(fn ($permission): string => $permission->value, $default->permissions());

                if ($role->wasRecentlyCreated) {
                    $createdRoles[] = $role->name;
                    $role->givePermissionTo($defaults);
                } else {
                    // Only what is new to the database: existing grants are the administrators' to change.
                    $new = array_values(array_intersect($defaults, $createdPermissions));

                    if ($new !== []) {
                        $role->givePermissionTo($new);
                    }
                }

                if ($role->isProtected()) {
                    $role->syncPermissions(PermissionCatalog::protectedRolePermissions());
                }
            }

            return ['permissions_created' => $createdPermissions, 'roles_created' => $createdRoles];
        });

        $this->registrar->forgetCachedPermissions();

        return $result;
    }
}
