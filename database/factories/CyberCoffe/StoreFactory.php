<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('loja-####'),
            'name' => fake()->company(),
            'status' => 'ativa',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inativa']);
    }
}
