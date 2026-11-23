<?php

namespace Database\Factories\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;
use App\Models\CyberCoffe\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StationSession>
 */
class StationSessionFactory extends Factory
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
            'station_id' => fn (array $attributes) => Station::factory()->create(['store_id' => $attributes['store_id']]),
            'customer_id' => null,
            'opened_by' => fn (array $attributes) => Attendant::factory()->create(['store_id' => $attributes['store_id']]),
            'billing' => 'pacote',
            'hourly_rate' => null,
            'started_at' => now(),
            'ends_at' => now()->addHour(),
            'amount_due' => '6.00',
            'status' => 'ativa',
        ];
    }

    /**
     * A closed session between two moments, with no amount due.
     */
    public function closedBetween(mixed $startedAt, mixed $closedAt): static
    {
        return $this->state(fn (array $attributes) => [
            'billing' => 'avulso',
            'hourly_rate' => '6.00',
            'started_at' => $startedAt,
            'ends_at' => null,
            'closed_at' => $closedAt,
            'status' => 'encerrada',
        ]);
    }
}
