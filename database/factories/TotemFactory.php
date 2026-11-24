<?php

namespace Database\Factories;

use App\Models\Totem;
use App\TotemStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Totem>
 */
class TotemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'T'.Totem::randomFromAlphabet(6),
            'name' => 'Totem '.fake()->unique()->numberBetween(1, 9999),
            'location' => null,
            'help_contact' => null,
            'status' => TotemStatus::Active,
            'supports_color' => true,
            'supports_duplex' => true,
            'price_bw_per_side' => '0.50',
            'price_color_per_side' => '1.50',
            'max_copies' => 20,
        ];
    }

    /**
     * Agent paired and sending heartbeats.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'paired_at' => now(),
            'last_seen_at' => now(),
            'printer_state' => 'idle',
            'printer_reasons' => [],
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TotemStatus::Revoked,
        ]);
    }
}
