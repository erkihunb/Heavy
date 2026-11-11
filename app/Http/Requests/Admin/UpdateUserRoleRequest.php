<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('updateRole', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // An empty role removes the person's access.
        return [
            'role' => ['present', 'nullable', 'string', Rule::in(Role::staffValues())],
        ];
    }

    public function role(): ?Role
    {
        return Role::tryFrom((string) $this->input('role'));
    }
}
