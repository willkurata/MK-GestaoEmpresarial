<?php

namespace App\Http\Resources\Totem;

use App\Actions\Totem\PageRange;
use App\Models\PrintOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Print job as delivered to the totem agent. The id is the idempotency key:
 * the agent must never print the same id twice.
 *
 * @mixin PrintOrder
 */
class AgentPrintJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'pickup_code' => $this->pickup_code,
            'status' => $this->status->value,
            'attempt' => $this->attempts,
            'lease_expires_at' => $this->lease_expires_at?->toIso8601String(),
            'file' => [
                'name' => $this->file_name,
                'mime' => $this->file_mime,
                'size_bytes' => $this->file_size,
                'sha256' => $this->file_sha256,
                'page_count' => $this->page_count,
                'download_url' => route('totem.agent.jobs.file', $this->resource),
            ],
            'options' => [
                'color' => $this->color_mode,
                'duplex' => $this->duplex,
                'pages_per_sheet' => $this->pages_per_sheet,
                'pages' => PageRange::pages($this->page_range, (int) $this->page_count),
                'copies' => $this->copies,
            ],
            'sheets_total' => $this->totalSheets(),
            'sheets_done' => $this->sheets_done,
        ];
    }
}
