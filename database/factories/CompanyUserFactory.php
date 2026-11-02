<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<CompanyUser>
 */
class CompanyUserFactory extends Factory
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
            'cpf' => fake()->unique()->numerify('###########'),
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => Hash::make('password'),
            'role' => 'Funcionario',
            'status' => 'ativa',
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Administrador',
        ]);
    }

    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Moderador',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inativa',
        ]);
    }
}
