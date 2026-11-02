<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
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
            'product_id' => Product::factory(),
            'product_name' => fake()->words(2, true),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->randomFloat(2, 1, 100),
            'unit_cost' => fake()->optional()->randomFloat(2, 0.5, 50),
        ];
    }
}
