<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vehicle as shown to members of the organization that registered it.
 *
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate' => $this->plate_normalized,
            'region_code' => $this->region_code,
            'owner_name' => $this->owner_name,
            'vehicle_type' => VehicleTypeResource::make($this->whenLoaded('vehicleType')),
            'registered_at' => EthiopianCalendar::present(
                $this->created_at?->setTimezone(config('localization.display_timezone')),
                app()->getLocale(),
            ),
        ];
    }
}
