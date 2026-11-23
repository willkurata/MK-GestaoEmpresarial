<?php

namespace App\Http\Resources\CyberCoffe;

use App\Models\CyberCoffe\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PrintJob
 */
class PrintJobResource extends JsonResource
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
            'kind' => $this->kind,
            'width' => $this->width,
            'status' => $this->status,
            'content' => $this->content,
            'payload_base64' => $this->payload,
            'station_session_id' => $this->station_session_id,
            'payment_id' => $this->payment_id,
            'printed_at' => $this->printed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
