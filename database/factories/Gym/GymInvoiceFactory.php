<?php

namespace Database\Factories\Gym;

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymInvoice>
 */
class GymInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_subscription_id' => GymSubscription::factory(),
            'gym_id' => fn (array $attributes) => GymSubscription::query()->whereKey($attributes['gym_subscription_id'])->value('gym_id'),
            'gym_student_id' => fn (array $attributes) => GymSubscription::query()->whereKey($attributes['gym_subscription_id'])->value('gym_student_id'),
            'amount' => '99.90',
            'period_start' => today()->toDateString(),
            'period_end' => today()->addMonth()->subDay()->toDateString(),
            'due_on' => today()->toDateString(),
            'status' => GymInvoice::STATUS_PENDING,
            'payment_method' => 'pix',
        ];
    }

    /**
     * Pending invoice whose due date passed the given number of days ago.
     */
    public function overdueBy(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => today()->subDays($days)->toDateString(),
            'period_end' => today()->subDays($days)->addMonth()->subDay()->toDateString(),
            'due_on' => today()->subDays($days)->toDateString(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GymInvoice::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
