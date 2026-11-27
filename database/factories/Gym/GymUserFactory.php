<?php

namespace Database\Factories\Gym;

use App\Models\Gym\Gym;
use App\Models\Gym\GymUnit;
use App\Models\Gym\GymUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<GymUser>
 */
class GymUserFactory extends Factory
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
            'gym_unit_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => GymUser::ROLE_MANAGER,
            'status' => 'ativo',
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => ['role' => GymUser::ROLE_ADMINISTRATOR]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => GymUser::ROLE_MANAGER]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes) => ['role' => GymUser::ROLE_STUDENT]);
    }

    public function forUnit(GymUnit $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'gym_id' => $unit->gym_id,
            'gym_unit_id' => $unit->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inativo']);
    }
}
