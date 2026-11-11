<?php

namespace App\Http\Requests\Officer;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReportPermitRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('report', $this->route('permit'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('verification.officer.report.note')];
    }
}
