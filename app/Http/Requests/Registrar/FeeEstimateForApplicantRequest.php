<?php

namespace App\Http\Requests\Registrar;

use App\Http\Requests\Applicant\FeeEstimateRequest;

class FeeEstimateForApplicantRequest extends FeeEstimateRequest
{
    use ActsForApplicant;

    public function authorize(): bool
    {
        return $this->mayActForApplicant();
    }
}
