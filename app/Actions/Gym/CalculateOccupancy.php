<?php

namespace App\Actions\Gym;

use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymUnit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Students currently inside each unit: their last allowed passage today was an entrance.
 */
class CalculateOccupancy
{
    /**
     * @param  EloquentCollection<int, GymUnit>  $units
     * @return list<array{unit_id: int, unit_name: string, capacity: int|null, present: int}>
     */
    public function handle(EloquentCollection $units): array
    {
        if ($units->isEmpty()) {
            return [];
        }

        $lastDirections = GymAccessEvent::query()
            ->whereIn('gym_unit_id', $units->modelKeys())
            ->where('decision', GymAccessEvent::ALLOWED)
            ->whereNotNull('gym_student_id')
            ->where('occurred_at', '>=', today())
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['gym_unit_id', 'gym_student_id', 'direction'])
            ->groupBy('gym_unit_id')
            ->map(fn (Collection $events) => $events->keyBy('gym_student_id')
                ->filter(fn (GymAccessEvent $event) => $event->direction === 'entrada')
                ->count());

        return array_values($units->map(fn (GymUnit $unit) => [
            'unit_id' => $unit->id,
            'unit_name' => $unit->name,
            'capacity' => $unit->capacity,
            'present' => (int) $lastDirections->get($unit->id, 0),
        ])->all());
    }
}
