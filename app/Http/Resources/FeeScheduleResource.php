<?php

namespace App\Http\Resources;

use App\Models\FeeSchedule;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A line of the fee schedule, as an Admin sees it.
 *
 * @mixin FeeSchedule
 */
class FeeScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $today = now(config('localization.display_timezone'))->toDateString();

        return [
            'id' => $this->id,
            'permit_type' => $this->permit_type->value,
            // null: the fee applies to every vehicle type without a fee of its own.
            'vehicle_type' => $this->vehicle_type_id === null
                ? null
                : VehicleTypeResource::make($this->whenLoaded('vehicleType')),
            'vehicle_type_id' => $this->vehicle_type_id,
            'min_days' => $this->min_days,
            'max_days' => $this->max_days,
            'amount' => Money::present($this->amount_cents),
            'effective_from' => EthiopianCalendar::present($this->effective_from, $locale),
            'effective_to' => EthiopianCalendar::present($this->effective_to, $locale),
            'is_current' => $this->effective_from->toDateString() <= $today
                && ($this->effective_to === null || $this->effective_to->toDateString() >= $today),
        ];
    }
}
