<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Caixa '.fake()->unique()->numberBetween(1, 999),
            'status' => 'ativo',
        ];
    }
}
