<?php

namespace App\Http\Requests\Staff;

use App\Enums\ExportDataset;
use App\Enums\ExportFormat;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Models\Export;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Export::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $statuses = $this->input('dataset') === ExportDataset::Payments->value
            ? array_map(fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases())
            : array_map(fn (PermitStatus $status): string => $status->value, array_filter(
                PermitStatus::cases(),
                fn (PermitStatus $status): bool => $status->isVisibleToStaff(),
            ));

        return [
            'dataset' => ['required', Rule::enum(ExportDataset::class)],
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dataset' => __('reports.exports.fields.dataset'),
            'format' => __('reports.exports.fields.format'),
            'status' => __('reports.exports.fields.status'),
            'from' => __('reports.exports.fields.from'),
            'to' => __('reports.exports.fields.to'),
        ];
    }

    /**
     * @return array{status: string|null, from: string|null, to: string|null}
     */
    public function filters(): array
    {
        return [
            'status' => $this->filled('status') ? (string) $this->validated('status') : null,
            'from' => $this->filled('from') ? (string) $this->validated('from') : null,
            'to' => $this->filled('to') ? (string) $this->validated('to') : null,
        ];
    }
}
