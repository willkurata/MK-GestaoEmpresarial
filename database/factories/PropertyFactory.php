<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'purpose' => 'venda',
            'type' => fake()->randomElement(Property::TYPES),
            'city' => fake()->city(),
            'neighborhood' => fake()->streetName(),
            'price' => fake()->randomFloat(2, 50000, 2000000),
            'description' => fake()->paragraph(),
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->numberBetween(1, 4),
            'parking_spaces' => fake()->numberBetween(0, 3),
            'area' => fake()->randomFloat(2, 30, 500),
            'features' => ['Piscina', 'Churrasqueira'],
            'status' => Property::STATUS_DRAFT,
        ];
    }

    public function forRent(): static
    {
        return $this->state(['purpose' => 'aluguel']);
    }

    /**
     * A listed property with one photo used as cover.
     */
    public function published(): static
    {
        return $this->state([
            'status' => Property::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->afterCreating(function (Property $property): void {
            $photo = PropertyPhoto::factory()->for($property)->create();
            $property->forceFill(['cover_photo_id' => $photo->id])->save();
        });
    }
}
