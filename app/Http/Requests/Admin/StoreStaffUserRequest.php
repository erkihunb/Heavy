<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesRoleChoice;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStaffUserRequest extends FormRequest
{
    use ValidatesRoleChoice;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            ...$this->roleRules(),
            // Where the person works. Empty means the head office. A closed branch takes no new staff.
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkRoleChoice($validator)];
    }

    public function branch(): ?Branch
    {
        return $this->filled('branch_id') ? Branch::query()->find($this->integer('branch_id')) : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('admin.users.fields.name'),
            'email' => __('admin.users.fields.email'),
            'roles' => __('rbac.users.fields.roles'),
            'roles.*' => __('rbac.users.fields.roles'),
            'branch_id' => __('admin.users.fields.branch'),
        ];
    }
}
