<?php

namespace App\Models;

use App\Enums\PermitStatus;
use App\Enums\VehicleRole;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * @return BelongsToMany<Permit, $this, PermitVehicle, 'pivot'>
     */
    public function permits(): BelongsToMany
    {
        return $this->belongsToMany(Permit::class, 'permit_vehicles')
            ->using(PermitVehicle::class)
            ->withPivot(['role', 'is_blocking']);
    }

    /**
     * Once the vehicle is on an application that has been submitted, its plate
     * is part of that record and can no longer be corrected by the applicant.
     */
    public function hasLockedPlate(): bool
    {
        return $this->permits()
            ->whereNotIn('status', [PermitStatus::Draft, PermitStatus::Withdrawn])
            ->exists();
    }

    /**
     * The vehicle's role on the permit it was loaded through
     * ($permit->vehicles), or null when it was not loaded through a permit.
     */
    public function roleOnPermit(): ?VehicleRole
    {
        $pivot = $this->relationLoaded('pivot') ? $this->getRelation('pivot') : null;

        return $pivot instanceof PermitVehicle ? $pivot->role : null;
    }
}
