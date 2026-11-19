<?php

namespace App\Support\Rbac;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;

/**
 * Every permission the application checks, in one list: permissions are not
 * put into categories. The role editor's picker, the permission screens and
 * RbacSynchronizer all read this list, and a test keeps it equal to
 * App\Enums\Permission, so a permission exists here exactly when code
 * checks it.
 *
 * Labels and descriptions live in lang/{en,am}/rbac.php
 * (`permission_labels`, `permission_descriptions`), keyed by name.
 *
 * Adding a permission: add the case to App\Enums\Permission, add it here,
 * give it a label in both languages, grant it to the default roles that need
 * it (App\Enums\Role::permissions()) and run
 * `php artisan db:seed --class=RolesAndPermissionsSeeder`. See the README.
 */
final class PermissionCatalog
{
    /**
     * The permission names, in the order screens show them.
     *
     * @var list<string>
     */
    public const array PERMISSIONS = [
        'dashboard.view',
        'permits.apply',
        'permits.view-queue',
        'permits.view-issued',
        'permits.review',
        'permits.approve',
        'permits.issue',
        'permits.revoke',
        'permits.comment',
        'institution-permits.issue',
        'institution-permits.view',
        'institution-permits.revoke',
        'institution-vehicles.view',
        'institution-vehicles.create',
        'institution-vehicles.update',
        'institution-vehicles.delete',
        'payments.verify',
        'payments.view',
        'applicants.register',
        'organizations.view',
        'organizations.update',
        'organizations.delete',
        'vehicles.update',
        'vehicles.delete',
        'permits.update-registered',
        'permits.delete-registered',
        'verification.scan',
        'reports.view',
        'users.view',
        'users.create',
        'users.update',
        'users.deactivate',
        'users.reset-passwords',
        'users.assign-roles',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.manage-global',
        'permissions.view',
        'settings.view',
        'settings.update',
        'branches.view',
        'branches.create',
        'branches.update',
        'branches.delete',
        'vehicle-types.view',
        'vehicle-types.create',
        'vehicle-types.update',
        'vehicle-types.delete',
        'fee-schedules.view',
        'fee-schedules.create',
        'fee-schedules.update',
        'fee-schedules.delete',
        'audit-log.view',
    ];

    /**
     * Permissions that say nothing about doing the office's work: the
     * dashboard, and what an applicant's account holds about its own
     * organization (User::isStaff()).
     *
     * @var list<string>
     */
    private const array NOT_OFFICE_WORK = [
        'dashboard.view',
        'permits.apply',
    ];

    /**
     * The role that holds every permission and can be neither deleted nor
     * narrowed (EUISIS "Super Admin").
     */
    public const string PROTECTED_ROLE = 'admin';

    /**
     * What the protected role always holds: every staff permission, which is
     * the administrator's default set. Not the applicant's own-organization
     * permissions: an administrator belongs to no organization.
     *
     * @return list<string>
     */
    public static function protectedRolePermissions(): array
    {
        return array_map(
            fn (Permission $permission): string => $permission->value,
            RoleEnum::Admin->permissions(),
        );
    }

    /**
     * The permissions of the office's work. Holding any of them makes
     * someone a member of staff (User::isStaff()).
     *
     * @return list<string>
     */
    public static function staffPermissions(): array
    {
        return array_values(array_diff(self::PERMISSIONS, self::NOT_OFFICE_WORK));
    }

    /**
     * Every permission name, in catalogue order.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return self::PERMISSIONS;
    }

    /**
     * Every permission with its label and description in the current
     * language, for the role editor's picker.
     *
     * @return list<array{name: string, label: string, description: string|null}>
     */
    public static function entries(): array
    {
        return array_map(fn (string $name): array => self::entry($name), self::PERMISSIONS);
    }

    /**
     * One permission with its label and description in the current language.
     *
     * @return array{name: string, label: string, description: string|null}
     */
    public static function entry(string $name): array
    {
        $labels = (array) trans('rbac.permission_labels');
        $descriptions = (array) trans('rbac.permission_descriptions');

        return [
            'name' => $name,
            'label' => is_string($labels[$name] ?? null) ? $labels[$name] : $name,
            'description' => is_string($descriptions[$name] ?? null) ? $descriptions[$name] : null,
        ];
    }
}
