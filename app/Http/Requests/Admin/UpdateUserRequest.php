<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member of staff's name and the branch they work at. Where they may see
 * applications is their branch scope, changed on its own card.
 */
class UpdateUserRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            // Empty means the head office. A closed branch takes no new staff, but someone already there may stay.
            'branch_id' => ['present', 'nullable', 'integer', Rule::exists(Branch::class, 'id')->where(
                fn ($query) => $query->where('is_active', true)->when($current !== null, fn ($query) => $query->orWhere('id', $current)),
            )],
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
            'branch_id' => __('admin.users.fields.branch'),
        ];
    }
}
