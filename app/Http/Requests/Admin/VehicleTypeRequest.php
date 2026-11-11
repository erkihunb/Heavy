<?php

namespace App\Http\Requests\Admin;

use App\Models\VehicleType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A vehicle type. Its code is a stable machine name, fixed once created.
 */
class VehicleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('vehicle_type');

        return $type instanceof VehicleType
            ? ($this->user()?->can('update', $type) ?? false)
            : ($this->user()?->can('create', VehicleType::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => is_string($this->input('code')) ? mb_strtolower(trim($this->input('code'))) : $this->input('code'),
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
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];

        if (! $this->route('vehicle_type') instanceof VehicleType) {
            $rules['code'] = ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique(VehicleType::class, 'code')];
        }

        return $rules;
    }

    /**
     * @return array{code?: string, name_am: string, name_en: string, is_active: bool, sort_order: int}
     */
    public function attributesToSave(): array
    {
        $values = [
            'name_am' => trim((string) $this->input('name_am')),
            'name_en' => trim((string) $this->input('name_en')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->input('sort_order'),
        ];

        if (! $this->route('vehicle_type') instanceof VehicleType) {
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
            'code' => __('admin.vehicle_types.fields.code'),
            'name_am' => __('admin.vehicle_types.fields.name_am'),
            'name_en' => __('admin.vehicle_types.fields.name_en'),
            'sort_order' => __('admin.vehicle_types.fields.sort_order'),
        ];
    }
}
