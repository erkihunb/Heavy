<?php

namespace App\Models;

use App\Enums\VehicleRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A vehicle on a permit. Rows are written by App\Domain\Permits\PermitVehicles,
 * which also keeps the period (`period_from`, `period_to`) and `is_blocking` in step
 * with the permit.
 *
 * @property string $permit_id
 * @property string $vehicle_id
 * @property VehicleRole $role
 * @property bool $is_blocking
 */
class PermitVehicle extends Pivot
{
    protected $table = 'permit_vehicles';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => VehicleRole::class,
            'is_blocking' => 'boolean',
        ];
    }
}
