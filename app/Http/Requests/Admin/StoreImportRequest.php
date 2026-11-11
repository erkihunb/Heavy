<?php

namespace App\Http\Requests\Admin;

use App\Models\ImportBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ImportBatch::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // A CSV is plain text; its content is sniffed, not its name.
            'file' => ['required', File::types(['csv', 'txt'])->max(50 * 1024)],
            'dry_run' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => __('imports.fields.file'),
            'dry_run' => __('imports.fields.dry_run'),
        ];
    }
}
