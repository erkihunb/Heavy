<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organization_id
 * @property int $vehicle_type_id
 * @property string $plate_raw
 * @property string $plate_normalized
 * @property string $region_code
 * @property string $owner_name
 * @property Carbon|null $created_at
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, HasUlids;

    /**
     * `organization_id` is deliberately not fillable: a vehicle is attached to
     * an organization by the RegisterVehicle action and never moves.
     *
     * @var list<string>
     */
    protected $fillable = [
        'vehicle_type_id',
        'plate_raw',
        'plate_normalized',
        'region_code',
        'owner_name',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
