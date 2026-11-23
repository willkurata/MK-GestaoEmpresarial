<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Payment;
use App\Models\CyberCoffe\Store;
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
            'store_id' => Store::factory(),
            'received_by' => fn (array $attributes) => Attendant::factory()->create(['store_id' => $attributes['store_id']]),
            'purpose' => 'sessao',
            'method' => 'pix',
            'amount' => '10.00',
            'paid_at' => now(),
        ];
    }
}
