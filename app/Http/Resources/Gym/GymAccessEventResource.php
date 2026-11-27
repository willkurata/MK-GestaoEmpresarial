<?php

namespace App\Http\Resources\Gym;

use App\Models\Gym\GymAccessEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GymAccessEvent
 */
class GymAccessEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->gym_unit_id,
            'device_id' => $this->gym_access_device_id,
            'student_id' => $this->gym_student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student?->name),
            'direction' => $this->direction,
            'method' => $this->method,
            'decision' => $this->decision,
            'reason' => $this->reason,
            'recorded_by_user_id' => $this->recorded_by_user_id,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
