<?php

namespace Database\Factories;

use App\Models\PrintOrder;
use App\Models\PrintPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintPayment>
 */
class PrintPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'print_order_id' => PrintOrder::factory()->priced(),
            'provider' => 'sandbox',
            'external_id' => 'sbx_'.fake()->uuid(),
            'amount' => '1.00',
            'status' => 'pending',
            'copy_paste' => 'SANDBOX-PIX',
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
