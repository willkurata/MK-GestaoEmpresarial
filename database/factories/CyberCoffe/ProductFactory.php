<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Product;
use App\Models\CyberCoffe\Store;
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
            'store_id' => Store::factory(),
            'name' => fake()->unique()->bothify('Produto ###'),
            'category' => 'produto',
            'price' => '5.00',
            'active' => true,
        ];
    }

    public function printing(string $pricePerPage = '0.50'): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->unique()->bothify('Impressão ###'),
            'category' => 'impressao',
            'price' => $pricePerPage,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
