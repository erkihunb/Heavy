<?php

namespace App\Http\Requests\Applicant;

use App\Models\Vehicle;

abstract class StoreVehicleRequest extends VehicleRequest
{
    abstract public function authorize(): bool;

    protected function vehicle(): ?Vehicle
    {
        return null;
    }
}
