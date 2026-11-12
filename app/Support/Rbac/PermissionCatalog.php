<?php

namespace App\Support\Rbac;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;

/**
 * Every permission the application checks, grouped for people: modules
 * (what part of the work) hold groups (what kind of thing), which hold
 * permissions named `group.action`. The role editor's picker, the
 * permission screens and RbacSynchronizer all read this list, and a test
 * keeps it equal to App\Enums\Permission, so a permission exists here
 * exactly when code checks it.
 *
 * Labels and descriptions live in lang/{en,am}/rbac.php
 * (`permission_labels`, `permission_descriptions`), keyed by name.
 *
 * Adding a permission: add the case to App\Enums\Permission, add it here
 * under its group, give it a label in both languages, grant it to the
 * default roles that need it (App\Enums\Role::permissions()) and run
 * `php artisan db:seed --class=RolesAndPermissionsSeeder`. See the README.
 */
final class PermissionCatalog
{
    /**
     * module => group => permission names, in the order screens show them.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const array MODULES = [
        'applicant' => [
            'organization' => ['organization.manage-own'],
            'vehicles' => ['vehicles.manage-own'],
            'applications' => ['permits.apply', 'payments.submit', 'permits.download-own'],
        ],
        'processing' => [
            'permits' => [
                'permits.view-queue',
                'permits.review',
                'permits.approve',
                'permits.issue',
                'permits.revoke',
                'permits.comment',
            ],
            'payments' => ['payments.verify'],
        ],
        'office' => [
            'applicants' => ['applicants.register'],
            'verification' => ['verification.scan'],
            'reports' => ['reports.view'],
        ],
        'administration' => [
            'users' => ['users.view', 'users.create', 'users.update', 'users.deactivate', 'users.assign-roles'],
            'roles' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete'],
            'permissions' => ['permissions.view'],
            'configuration' => ['settings.manage', 'vehicle-types.manage', 'fee-schedules.manage'],
            'audit' => ['audit-log.view'],
        ],
    ];

    /**
     * The role that holds every permission and can be neither deleted nor
     * narrowed (EUISIS "Super Admin").
     */
    public const string PROTECTED_ROLE = 'admin';

    /**
     * What the protected role always holds: every staff permission, which is
     * the administrator's default set. Not the applicant's own-organization
     * permissions: an administrator belongs to no organization, and those
     * pages refuse anyone without one.
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
     * Every permission name, in catalogue order.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (self::MODULES as $groups) {
            foreach ($groups as $permissions) {
                array_push($names, ...$permissions);
            }
        }

        return $names;
    }

    /**
     * The group a permission is listed under, or null when it is not in the catalogue.
     */
    public static function groupOf(string $permission): ?string
    {
        foreach (self::MODULES as $groups) {
            foreach ($groups as $group => $permissions) {
                if (in_array($permission, $permissions, true)) {
                    return $group;
                }
            }
        }

        return null;
    }

    /**
     * The module a permission is listed under, or null when it is not in the catalogue.
     */
    public static function moduleOf(string $permission): ?string
    {
        foreach (self::MODULES as $module => $groups) {
            foreach ($groups as $permissions) {
                if (in_array($permission, $permissions, true)) {
                    return $module;
                }
            }
        }

        return null;
    }

    /**
     * The modules as the screens list them: key, label and the groups inside.
     *
     * @return list<array{key: string, name: string, groups: list<string>}>
     */
    public static function modules(): array
    {
        return array_map(
            fn (string $module): array => [
                'key' => $module,
                'name' => self::line("rbac.modules.{$module}", $module),
                'groups' => array_keys(self::MODULES[$module]),
            ],
            array_keys(self::MODULES),
        );
    }

    /**
     * group => permissions with their label and description in the current
     * language, for the role editor's picker.
     *
     * @return array<string, list<array{name: string, label: string, description: string|null}>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::MODULES as $groups) {
            foreach ($groups as $group => $permissions) {
                $grouped[$group] = array_map(fn (string $name): array => self::entry($name), $permissions);
            }
        }

        return $grouped;
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

    private static function line(string $key, string $fallback): string
    {
        $line = trans($key);

        return is_string($line) && $line !== $key ? $line : $fallback;
    }
}
