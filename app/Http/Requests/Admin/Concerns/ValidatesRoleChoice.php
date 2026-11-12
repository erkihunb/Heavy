<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The roles an administrator may give a member of staff: one or more roles
 * other than the applicant's. The administrator role and global roles see
 * every branch, so only an administrator gives them (RolePolicy::manageGlobal).
 */
trait ValidatesRoleChoice
{
    /**
     * @return array<string, mixed>
     */
    protected function roleRules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::exists(Role::class, 'name')->where('guard_name', 'web'),
                Rule::notIn([RoleEnum::Applicant->value]),
            ],
        ];
    }

    protected function checkRoleChoice(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || ($this->user()?->can('manageGlobal', Role::class) ?? false)) {
            return;
        }

        $wide = Role::query()
            ->whereIn('name', (array) $this->input('roles', []))
            ->where(fn ($query) => $query->where('name', PermissionCatalog::PROTECTED_ROLE)->orWhere('scope_type', Role::SCOPE_GLOBAL))
            ->exists();

        if ($wide) {
            $validator->errors()->add('roles', __('rbac.users.errors.global_admin_only'));
        }
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return array_values(array_map('strval', (array) $this->input('roles', [])));
    }
}
