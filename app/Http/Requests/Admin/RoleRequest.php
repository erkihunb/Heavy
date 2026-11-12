<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A role's names, scope type and permissions, for creating or changing one
 * (EUISIS RoleStoreRequest + RoleUpdateRequest). Authorisation is the
 * controller's (RolePolicy); this checks the values.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->role();

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                // Letters (any script), digits, spaces, dots, dashes and underscores.
                'regex:/^[\pL\pN][\pL\pN ._-]*$/u',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id),
            ],
            'name_am' => ['nullable', 'string', 'max:100'],
            'scope_type' => ['required', Rule::in([Role::SCOPE_BRANCH, Role::SCOPE_GLOBAL])],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalog::names())],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = $this->role();
                $user = $this->user();

                // A default role's code name is used by the application.
                if ($role?->is_system && $this->input('name') !== $role->name) {
                    $validator->errors()->add('name', __('rbac.roles.errors.rename_system'));
                }

                // Making a role global (or creating one) lifts every branch limit: administrators only.
                $becomesGlobal = $this->input('scope_type') === Role::SCOPE_GLOBAL && ! $role?->isGlobal();

                if ($becomesGlobal && ! $user?->can('manageGlobal', Role::class)) {
                    $validator->errors()->add('scope_type', __('rbac.roles.errors.global_admin_only'));
                }
            },
        ];
    }

    public function role(): ?Role
    {
        $role = $this->route('role');

        return $role instanceof Role ? $role : null;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_values(array_unique((array) $this->validated('permissions', [])));
    }

    public function attributes(): array
    {
        return [
            'name' => __('rbac.roles.name'),
            'name_am' => __('rbac.roles.nameAm'),
            'scope_type' => __('rbac.roles.scopeType'),
            'permissions' => __('rbac.roles.permissionsSection'),
        ];
    }
}
