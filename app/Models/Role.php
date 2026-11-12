<?php

namespace App\Models;

use App\Support\Rbac\PermissionCatalog;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role, editable on the Roles screen (EUISIS RBAC). The default roles
 * (App\Enums\Role) are seeded with `is_system`: their code name is used by
 * the application and cannot change, and they cannot be deleted. The
 * administrator role (PermissionCatalog::PROTECTED_ROLE) always holds every
 * permission.
 *
 * `scope_type` says where the role's permissions apply: "branch" (only the
 * branches in the user's scope rows; no row, nothing; fail closed) or
 * "global" (everywhere).
 *
 * @property int $id
 * @property string $name
 * @property string|null $name_am
 * @property string $scope_type
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    public const string SCOPE_BRANCH = 'branch';

    public const string SCOPE_GLOBAL = 'global';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function isProtected(): bool
    {
        return $this->name === PermissionCatalog::PROTECTED_ROLE;
    }

    public function isGlobal(): bool
    {
        return $this->scope_type === self::SCOPE_GLOBAL;
    }

    /**
     * The role's name in a language: a default role's from lang/{en,am}/roles.php,
     * a created role's from its own columns (name, name_am).
     */
    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'am' && filled($this->name_am)) {
            return $this->name_am;
        }

        $key = "roles.{$this->name}.name";
        $line = trans($key, [], $locale);

        return is_string($line) && $line !== $key ? $line : $this->name;
    }
}
