<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Http\Requests\Admin\Concerns\ValidatesRoleChoice;
use App\Http\Requests\Admin\Concerns\ValidatesStaffProfile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStaffUserRequest extends FormRequest
{
    use PasswordValidationRules;
    use ValidatesRoleChoice;
    use ValidatesStaffProfile;

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
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            ...$this->roleRules(),
            // Where the person works. Empty means the head office. A closed branch takes no new staff.
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')->where('is_active', true)],
            'password' => $this->filled('password')
                ? $this->passwordRules(personal: array_map(fn (mixed $value): ?string => is_string($value) ? $value : null, $this->only(['name', 'email', 'username', 'phone', 'first_name', 'middle_name', 'last_name'])))
                : ['nullable'],
            ...$this->profileRules(),
            'active' => ['sometimes', 'boolean'],
            ...$this->directPermissionRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkRoleChoice($validator)];
    }

    /**
     * The starting password the Admin typed, when they chose one.
     */
    public function startingPassword(): ?string
    {
        return $this->filled('password') ? $this->string('password')->toString() : null;
    }

    /**
     * Whether the account may sign in from the start; it is active unless the Admin said otherwise.
     */
    public function active(): bool
    {
        return ! $this->has('active') || $this->boolean('active');
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
            'password' => __('rbac.users.startingPassword'),
            ...$this->profileAttributes(),
        ];
    }
}
