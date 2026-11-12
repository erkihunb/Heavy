<?php

namespace App\Http\Requests\Registrar;

use App\Http\Requests\Applicant\StorePermitRequest;

class StorePermitForApplicantRequest extends StorePermitRequest
{
    use ActsForApplicant;

    public function authorize(): bool
    {
        return $this->mayActForApplicant();
    }
}
