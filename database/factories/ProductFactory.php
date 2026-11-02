<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
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
            'name' => fake()->words(2, true),
            'sale_price' => fake()->randomFloat(2, 1, 500),
            'cost' => fake()->optional()->randomFloat(2, 0.5, 250),
            'status' => 'ativo',
        ];
    }
}
