<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A branch office. Its code is printed in permit numbers and fixed once created.
 */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            ? ($this->user()?->can('update', $branch) ?? false)
            : ($this->user()?->can('create', Branch::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => is_string($this->input('code')) ? mb_strtoupper(trim($this->input('code'))) : $this->input('code'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name_am' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];

        if (! $this->route('branch') instanceof Branch) {
            // Capitals and digits only: the code becomes part of permit numbers.
            $rules['code'] = ['required', 'string', 'min:2', 'max:6', 'regex:/^[A-Z0-9]+$/', Rule::unique(Branch::class, 'code')];
        }

        return $rules;
    }

    /**
     * @return array{code?: string, name_am: string, name_en: string, is_active: bool}
     */
    public function attributesToSave(): array
    {
        $values = [
            'name_am' => trim((string) $this->input('name_am')),
            'name_en' => trim((string) $this->input('name_en')),
            'is_active' => $this->boolean('is_active'),
        ];

        if (! $this->route('branch') instanceof Branch) {
            $values['code'] = (string) $this->input('code');
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('admin.branches.fields.code'),
            'name_am' => __('admin.branches.fields.name_am'),
            'name_en' => __('admin.branches.fields.name_en'),
        ];
    }
}
