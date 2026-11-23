<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Attendant>
 */
class AttendantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => Hash::make('password'),
            'role' => 'atendente',
            'status' => 'ativo',
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'gerente']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inativo']);
    }
}
