<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numerify('####');

        return [
            'code' => "B{$number}",
            'name_am' => "ቅርንጫፍ {$number}",
            'name_en' => "Branch {$number}",
            'is_active' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
