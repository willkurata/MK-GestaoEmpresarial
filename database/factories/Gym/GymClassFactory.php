<?php

namespace Database\Factories\Gym;

use App\Models\Gym\Gym;
use App\Models\Gym\GymClass;
use App\Models\Gym\GymUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymClass>
 */
class GymClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'gym_unit_id' => fn (array $attributes) => GymUnit::factory()->create(['gym_id' => $attributes['gym_id']])->id,
            'name' => 'Turma '.fake()->unique()->word(),
            'modality' => fake()->randomElement(['Pilates', 'Funcional', 'Spinning', 'Yoga']),
            'instructor_name' => fake()->name(),
            'schedule' => [
                ['weekday' => 2, 'starts_at' => '19:00', 'ends_at' => '20:00'],
                ['weekday' => 4, 'starts_at' => '19:00', 'ends_at' => '20:00'],
            ],
            'capacity' => 20,
            'status' => 'ativa',
        ];
    }

    public function forUnit(GymUnit $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'gym_id' => $unit->gym_id,
            'gym_unit_id' => $unit->id,
        ]);
    }
}
