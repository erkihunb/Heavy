<?php

namespace App\Http\Requests\Applicant;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Submitting sends no fields: the saved application is what gets submitted.
 */
class SubmitPermitRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('submit', $this->route('permit'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
