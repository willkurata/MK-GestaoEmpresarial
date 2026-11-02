<?php

namespace Database\Factories;

use App\Models\CashMovement;
use App\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'company_id' => fn (array $attributes) => CashSession::find($attributes['cash_session_id'])->company_id,
            'company_user_id' => fn (array $attributes) => CashSession::find($attributes['cash_session_id'])->company_user_id,
            'type' => 'retirada',
            'method' => 'Dinheiro',
            'amount' => -fake()->randomFloat(2, 1, 200),
            'description' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
