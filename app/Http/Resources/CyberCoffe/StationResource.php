<?php

namespace App\Http\Resources\CyberCoffe;

use App\Models\CyberCoffe\Station;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Station
 */
class StationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'active_session' => $this->whenLoaded('activeSession', fn () => $this->activeSession
                ? new StationSessionResource($this->activeSession)
                : null),
        ];
    }
}
