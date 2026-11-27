<?php

namespace Database\Factories\Gym;

use App\Models\Gym\Gym;
use App\Models\Gym\GymPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymPlan>
 */
class GymPlanFactory extends Factory
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
            'name' => 'Plano '.fake()->unique()->word(),
            'description' => null,
            'price' => '99.90',
            'billing_interval_months' => 1,
            'grace_days' => 5,
            'status' => 'ativo',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inativo']);
    }
}
