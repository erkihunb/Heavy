<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_am' => 'ተቋም '.fake()->unique()->numerify('#####'),
            'name_en' => fake()->company(),
            'tin' => fake()->unique()->numerify('##########'),
            'phone' => '+2519'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'created_by' => User::factory(),
        ];
    }
}
