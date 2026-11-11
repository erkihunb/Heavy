<?php

namespace App\Http\Requests\Applicant;

use App\Models\Vehicle;

class StoreVehicleRequest extends VehicleRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Vehicle::class);
    }

    protected function vehicle(): ?Vehicle
    {
        return null;
    }
}
