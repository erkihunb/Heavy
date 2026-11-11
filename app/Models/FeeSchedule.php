<?php

namespace App\Models;

use App\Domain\Permits\FeeCalculator;
use App\Enums\PermitType;
use Carbon\CarbonImmutable;
use Database\Factories\FeeScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the fee schedule: what a permit of a type, for a vehicle type,
 * covering a number of days costs while the line is in force (spec §4.4).
 *
 * @property int $id
 * @property PermitType $permit_type
 * @property int|null $vehicle_type_id
 * @property int $min_days
 * @property int $max_days
 * @property int $amount_cents
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 */
class FeeSchedule extends Model
{
    /** @use HasFactory<FeeScheduleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'permit_type',
        'vehicle_type_id',
        'min_days',
        'max_days',
        'amount_cents',
        'effective_from',
        'effective_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permit_type' => PermitType::class,
            'vehicle_type_id' => 'integer',
            'min_days' => 'integer',
            'max_days' => 'integer',
            'amount_cents' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The calculator caches the schedule; any change replaces the cache.
        static::saved(fn () => FeeCalculator::forgetCache());
        static::deleted(fn () => FeeCalculator::forgetCache());
    }

    /**
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
