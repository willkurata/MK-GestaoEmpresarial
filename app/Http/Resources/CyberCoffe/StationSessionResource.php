<?php

namespace App\Http\Resources\CyberCoffe;

use App\Models\CyberCoffe\SessionCharge;
use App\Models\CyberCoffe\StationSession;
use Carbon\CarbonInterface;
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
        $isOpenAndActive = $this->isActive() && ! $this->isPackage();

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
            'billing_increment_minutes' => $this->isPackage() ? null : $this->billing_increment_minutes,
            'billable_minutes' => $isOpenAndActive ? $this->billableMinutes($now) : null,
            'current_amount' => $isOpenAndActive ? $this->money($this->openAmountInCents($now)) : null,
            'projected_total' => $this->money($this->projectedTotalInCents($now)),
            'amount_due' => $this->money($amountDueInCents),
            'amount_paid' => $this->money($amountPaidInCents),
            'balance_due' => $this->money(max(0, $amountDueInCents - $amountPaidInCents)),
            'charges' => $this->whenLoaded('charges', fn () => $this->charges->map(fn (SessionCharge $charge) => [
                'id' => $charge->id,
                'kind' => $charge->kind,
                'product_id' => $charge->product_id,
                'description' => $charge->description,
                'minutes' => $charge->minutes,
                'quantity' => $charge->quantity,
                'unit_price' => $charge->unit_price,
                'manual_price' => $charge->manual_price,
                'added_by_agent' => ! $charge->isTime() && $charge->created_by === null,
                'amount' => $charge->amount,
                'created_at' => $charge->created_at?->toIso8601String(),
            ])),
            'bill' => $this->whenLoaded('charges', fn () => $this->bill($now)),
            'server_time' => $now->toIso8601String(),
        ];
    }

    /**
     * Final-bill breakdown. For an active open session the time line is the
     * amount accrued so far.
     *
     * @return array{time_amount: string, products_amount: string, prints_amount: string, printed_pages: int, total: string}
     */
    private function bill(CarbonInterface $now): array
    {
        $timeInCents = $this->charges->where('kind', 'tempo')->sum(fn (SessionCharge $charge): int => $charge->amountInCents());

        if ($this->isActive() && ! $this->isPackage()) {
            $timeInCents += $this->openAmountInCents($now);
        }

        $prints = $this->charges->where('kind', 'impressao');

        return [
            'time_amount' => $this->money($timeInCents),
            'products_amount' => $this->money($this->charges->where('kind', 'produto')->sum(fn (SessionCharge $charge): int => $charge->amountInCents())),
            'prints_amount' => $this->money($prints->sum(fn (SessionCharge $charge): int => $charge->amountInCents())),
            'printed_pages' => (int) $prints->sum('quantity'),
            'total' => $this->money($this->projectedTotalInCents($now)),
        ];
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
