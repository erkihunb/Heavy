<?php

namespace App\Http\Resources;

use App\Models\PermitVehicle;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vehicle as it appears on a permit: its plate and what it is on that
 * permit (the primary vehicle or a trailer).
 *
 * @mixin Vehicle
 *
 * @property-read PermitVehicle $pivot the row of permit_vehicles the vehicle was loaded through
 */
class PermitVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate' => $this->plate_normalized,
            'role' => $this->pivot->role->value,
            'owner_name' => $this->owner_name,
            'vehicle_type' => VehicleTypeResource::make($this->whenLoaded('vehicleType')),
        ];
    }
}
