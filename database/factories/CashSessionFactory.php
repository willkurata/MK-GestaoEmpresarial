<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CompanyUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
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
            'company_id' => fn (array $attributes) => CashRegister::find($attributes['cash_register_id'])->company_id,
            'company_user_id' => CompanyUser::factory(),
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'aberto',
        ];
    }
}
