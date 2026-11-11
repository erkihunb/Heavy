<?php

namespace App\Http\Requests\Applicant;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class WithdrawPermitRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('withdraw', $this->route('permit'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
