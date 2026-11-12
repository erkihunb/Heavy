<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesRoleChoice;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The roles a member of staff holds (several allowed). Taking every role
 * away is deactivating the account instead.
 */
class UpdateUserRolesRequest extends FormRequest
{
    use ValidatesRoleChoice;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('assignRoles', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->roleRules();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkRoleChoice($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'roles' => __('rbac.users.fields.roles'),
            'roles.*' => __('rbac.users.fields.roles'),
        ];
    }
}
