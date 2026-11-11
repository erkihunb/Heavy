<?php

namespace Database\Factories;

use App\Domain\Vehicles\PlateNumber;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'owner_name' => fake()->name(),
            ...$this->plateAttributes('ኢት-03-'.fake()->unique()->numerify('9####')),
        ];
    }

    /**
     * Give the vehicle a specific plate, stored exactly as the application would store it.
     */
    public function withPlate(string $plate): static
    {
        return $this->state(fn (): array => $this->plateAttributes($plate));
    }

    /**
     * @return array{plate_raw: string, plate_normalized: string, region_code: string}
     */
    private function plateAttributes(string $plate): array
    {
        $parsed = PlateNumber::parse($plate);

        return [
            'plate_raw' => $plate,
            'plate_normalized' => $parsed->normalized,
            'region_code' => $parsed->region->value,
        ];
    }
}
