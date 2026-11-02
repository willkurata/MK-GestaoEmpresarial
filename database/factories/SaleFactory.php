<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CompanyUser;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'cash_session_id' => CashSession::factory(),
            'company_id' => fn (array $attributes) => CashSession::find($attributes['cash_session_id'])->company_id,
            'company_user_id' => CompanyUser::factory(),
            'occurred_at' => now(),
            'status' => 'finalizada',
            'subtotal' => 10,
            'discount' => 0,
            'total' => 10,
        ];
    }
}
