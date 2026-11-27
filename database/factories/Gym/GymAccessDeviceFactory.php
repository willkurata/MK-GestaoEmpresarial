<?php

namespace Database\Factories\Gym;

use App\Models\Gym\GymAccessDevice;
use App\Models\Gym\GymUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GymAccessDevice>
 */
class GymAccessDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_unit_id' => GymUnit::factory(),
            'gym_id' => fn (array $attributes) => GymUnit::query()->whereKey($attributes['gym_unit_id'])->value('gym_id'),
            'name' => 'Catraca '.fake()->unique()->numberBetween(1, 999),
            'vendor' => 'controlid',
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => 'ativo',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inativo']);
    }
}
