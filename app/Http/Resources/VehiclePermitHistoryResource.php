<?php

namespace App\Http\Resources;

use App\Models\Permit;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Another permit of a vehicle, as listed for staff next to the application
 * they are reviewing.
 *
 * @mixin Permit
 */
class VehiclePermitHistoryResource extends JsonResource
{
    /**
     * @param  bool  $conflicts  the permit reserves the vehicle for days the application under review also asks for
     */
    public function __construct(Permit $permit, private readonly bool $conflicts = false)
    {
        parent::__construct($permit);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'permit_number' => $this->permit_number,
            'status' => $this->status->value,
            'effective_status' => $this->effectiveStatus(CarbonImmutable::now())->value,
            'permit_type' => $this->permit_type->value,
            'valid_from' => EthiopianCalendar::present($this->valid_from, $locale),
            'valid_to' => EthiopianCalendar::present($this->valid_to, $locale),
            'conflicts' => $this->conflicts,
        ];
    }
}
