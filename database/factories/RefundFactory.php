<?php

namespace Database\Factories;

use App\Models\CompanyUser;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'company_id' => fn (array $attributes) => Payment::find($attributes['payment_id'])->company_id,
            'company_user_id' => CompanyUser::factory(),
            'amount' => 10,
            'method' => 'dinheiro',
            'reason' => fake()->sentence(),
            'refunded_at' => now(),
        ];
    }
}
