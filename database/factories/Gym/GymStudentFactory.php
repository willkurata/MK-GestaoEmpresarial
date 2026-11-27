<?php

namespace Database\Factories\Gym;

use App\Models\Gym\Gym;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymStudent>
 */
class GymStudentFactory extends Factory
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
            'name' => fake()->name(),
            'cpf' => fake()->unique()->numerify('###########'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('119########'),
            'birth_date' => '1995-05-10',
            'status' => 'ativo',
        ];
    }

    public function forUnit(GymUnit $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'gym_id' => $unit->gym_id,
            'gym_unit_id' => $unit->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inativo']);
    }

    public function minor(): static
    {
        return $this->state(fn (array $attributes) => ['birth_date' => now()->subYears(15)->toDateString()]);
    }

    public function faceEnrolled(): static
    {
        return $this->state(fn (array $attributes) => [
            'face_status' => GymStudent::FACE_ENROLLED,
            'face_enrolled_at' => now(),
        ]);
    }
}
