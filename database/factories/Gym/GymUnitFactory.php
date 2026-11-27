<?php

namespace Database\Factories\Gym;

use App\Models\Gym\Gym;
use App\Models\Gym\GymUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymUnit>
 */
class GymUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => 'Unidade '.fake()->unique()->city(),
            'address' => fake()->streetAddress(),
            'capacity' => 80,
            'status' => 'ativa',
        ];
    }
}
