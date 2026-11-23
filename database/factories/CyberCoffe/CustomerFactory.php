<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'phone' => fake()->unique()->numerify('119########'),
            'document' => null,
            'password' => Hash::make('password'),
            'status' => 'ativo',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inativo']);
    }
}
