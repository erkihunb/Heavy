<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesStaffProfile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member of staff's name, e-mail (their sign-in name), profile, photo and the branch they work at. Where they may see
 * applications is their branch scope, changed on its own card.
 */
class UpdateUserRequest extends FormRequest
{
    use ValidatesStaffProfile;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $current = $target instanceof User ? $target->branch_id : null;

        return [
            // Sent by the form; left out, the address stays as it is.
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')->ignore($target instanceof User ? $target->id : null)],
            // Empty means the head office. A closed branch takes no new staff, but someone already there may stay.
            'branch_id' => ['present', 'nullable', 'integer', Rule::exists(Branch::class, 'id')->where(
                fn ($query) => $query->where('is_active', true)->when($current !== null, fn ($query) => $query->orWhere('id', $current)),
            )],
            ...$this->profileRules($target instanceof User ? $target : null),
            'remove_avatar' => ['boolean'],
        ];
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
            'branch_id' => __('admin.users.fields.branch'),
            ...$this->profileAttributes(),
        ];
    }
}
