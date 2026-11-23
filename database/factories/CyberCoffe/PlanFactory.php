<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Plan;
use App\Models\CyberCoffe\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
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
            'name' => '1 hora',
            'billing' => 'pacote',
            'price' => '6.00',
            'minutes' => 60,
            'hourly_rate' => null,
            'billing_increment_minutes' => null,
            'active' => true,
        ];
    }

    public function open(string $hourlyRate = '6.00', int $billingIncrementMinutes = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Tempo livre',
            'billing' => 'avulso',
            'price' => null,
            'minutes' => null,
            'hourly_rate' => $hourlyRate,
            'billing_increment_minutes' => $billingIncrementMinutes,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
