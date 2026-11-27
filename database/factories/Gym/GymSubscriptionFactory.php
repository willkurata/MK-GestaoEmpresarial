<?php

namespace Database\Factories\Gym;

use App\Models\Gym\GymPlan;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymSubscription>
 */
class GymSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_student_id' => GymStudent::factory(),
            'gym_id' => fn (array $attributes) => GymStudent::query()->whereKey($attributes['gym_student_id'])->value('gym_id'),
            'gym_plan_id' => fn (array $attributes) => GymPlan::factory()->create(['gym_id' => $attributes['gym_id']])->id,
            'price' => '99.90',
            'billing_interval_months' => 1,
            'payment_method' => 'pix',
            'status' => 'ativa',
            'starts_on' => today()->toDateString(),
            'next_billing_on' => today()->addMonth()->toDateString(),
        ];
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelada',
            'canceled_at' => now(),
            'cancellation_reason' => 'Pedido do aluno',
        ]);
    }
}
