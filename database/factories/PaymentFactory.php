<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'company_id' => fn (array $attributes) => Sale::find($attributes['sale_id'])->company_id,
            'method' => fake()->randomElement(['pix', 'dinheiro']),
            'amount' => 10,
            'status' => 'confirmado',
            'paid_at' => now(),
            'amount_received' => 10,
            'change_amount' => 0,
        ];
    }
}
