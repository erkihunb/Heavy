<?php

namespace App\Http\Requests\Registrar;

use App\Http\Requests\Applicant\StoreVehicleRequest;

class StoreVehicleForApplicantRequest extends StoreVehicleRequest
{
    use ActsForApplicant;

    public function authorize(): bool
    {
        return $this->mayActForApplicant();
    }
}
