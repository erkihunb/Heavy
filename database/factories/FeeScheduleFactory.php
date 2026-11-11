<?php

namespace Database\Factories;

use App\Enums\PermitType;
use App\Models\FeeSchedule;
use App\Models\VehicleType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeSchedule>
 */
class FeeScheduleFactory extends Factory
{
    /**
     * A fee for every vehicle type and every duration, in force since 2020.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permit_type' => PermitType::FullDay,
            'vehicle_type_id' => null,
            'min_days' => 1,
            'max_days' => 3650,
            'amount_cents' => 100_000,
            'effective_from' => '2020-01-01',
            'effective_to' => null,
        ];
    }

    public function forType(PermitType $type): static
    {
        return $this->state(['permit_type' => $type]);
    }

    public function forVehicleType(VehicleType|int $vehicleType): static
    {
        return $this->state(['vehicle_type_id' => $vehicleType instanceof VehicleType ? $vehicleType->id : $vehicleType]);
    }

    public function days(int $min, int $max): static
    {
        return $this->state(['min_days' => $min, 'max_days' => $max]);
    }

    public function amount(int $cents): static
    {
        return $this->state(['amount_cents' => $cents]);
    }

    public function effective(DateTimeInterface|string $from, DateTimeInterface|string|null $to = null): static
    {
        $date = fn (DateTimeInterface|string|null $value): ?string => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;

        return $this->state(['effective_from' => $date($from), 'effective_to' => $date($to)]);
    }
}
