<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
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
            'name' => fake()->unique()->bothify('PC-##'),
            'type' => 'pc',
            'status' => 'livre',
        ];
    }

    public function inMaintenance(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'manutencao']);
    }
}
