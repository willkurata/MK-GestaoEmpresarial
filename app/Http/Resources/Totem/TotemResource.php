<?php

namespace App\Http\Resources\Totem;

use App\Models\Totem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Totem as seen by the MK operations panel.
 *
 * @mixin Totem
 */
class TotemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'location' => $this->location,
            'help_contact' => $this->help_contact,
            'status' => $this->status->value,
            'paired' => $this->api_token_hash !== null,
            'online' => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'agent_version' => $this->agent_version,
            'printer' => [
                'state' => $this->displayPrinterState(),
                'reasons' => $this->printer_reasons ?? [],
                'paper_level' => $this->paper_level,
                'markers' => $this->printer_markers ?? [],
            ],
            'supports_color' => $this->supports_color,
            'supports_duplex' => $this->supports_duplex,
            'price_bw_per_side' => $this->price_bw_per_side,
            'price_color_per_side' => $this->price_color_per_side,
            'max_copies' => $this->max_copies,
            'queue' => $this->whenCounted('queued_orders_count', fn (): array => [
                'queued' => $this->queued_orders_count,
                'printing' => $this->printing_orders_count,
                'failed' => $this->failed_orders_count,
            ]),
        ];
    }
}
