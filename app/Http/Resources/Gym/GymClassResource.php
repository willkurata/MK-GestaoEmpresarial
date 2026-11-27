<?php

namespace App\Http\Resources\Gym;

use App\Models\Gym\GymClass;
use App\Models\Gym\GymStudent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GymClass
 */
class GymClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->gym_unit_id,
            'name' => $this->name,
            'modality' => $this->modality,
            'instructor_name' => $this->instructor_name,
            'schedule' => $this->schedule,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'enrolled_count' => $this->whenCounted('currentStudents'),
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn (GymStudent $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'status' => $student->status,
                'joined_on' => substr((string) $student->getRelationValue('pivot')->joined_on, 0, 10),
                'left_on' => $student->getRelationValue('pivot')->left_on === null ? null : substr((string) $student->getRelationValue('pivot')->left_on, 0, 10),
            ])),
        ];
    }
}
