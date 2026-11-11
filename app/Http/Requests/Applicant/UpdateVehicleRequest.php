<?php

namespace App\Http\Requests\Applicant;

use App\Models\Vehicle;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdateVehicleRequest extends VehicleRequest
{
    /**
     * Returning the policy's response keeps its "not found" answer for a
     * vehicle that belongs to another organization.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->vehicle());
    }

    protected function vehicle(): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return $vehicle;
    }
}
