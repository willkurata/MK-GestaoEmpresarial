<?php

namespace App\Http\Resources\CyberCoffe;

use App\Models\CyberCoffe\StationSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StationSession
 */
class StationSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = now();
        $amountPaidInCents = $this->amountPaidInCents();
        $amountDueInCents = $this->amountDueInCents();

        return [
            'id' => $this->id,
            'station_id' => $this->station_id,
            'station' => $this->whenLoaded('station', fn () => ['id' => $this->station->id, 'name' => $this->station->name]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'billing' => $this->billing,
            'hourly_rate' => $this->hourly_rate,
            'status' => $this->status,
            'started_at' => $this->started_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'elapsed_seconds' => $this->elapsedSeconds($now),
            'remaining_seconds' => $this->remainingSeconds($now),
            'expired' => $this->isExpired($now),
            'current_amount' => $this->isActive() && ! $this->isPackage()
                ? number_format($this->openAmountInCents($now) / 100, 2, '.', '')
                : null,
            'amount_due' => number_format($amountDueInCents / 100, 2, '.', ''),
            'amount_paid' => number_format($amountPaidInCents / 100, 2, '.', ''),
            'balance_due' => number_format(max(0, $amountDueInCents - $amountPaidInCents) / 100, 2, '.', ''),
            'charges' => $this->whenLoaded('charges', fn () => $this->charges->map(fn ($charge) => [
                'description' => $charge->description,
                'minutes' => $charge->minutes,
                'amount' => $charge->amount,
            ])),
            'server_time' => $now->toIso8601String(),
        ];
    }
}
