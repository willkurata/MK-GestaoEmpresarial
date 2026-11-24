<?php

namespace App\Http\Resources\Totem;

use App\Actions\Totem\QrCodeSvg;
use App\Actions\Totem\QrSessionToken;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the kiosk screen needs in a single poll.
 *
 * @mixin Totem
 */
class TotemDisplayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $qrSession = app(QrSessionToken::class)->issue($this->resource);
        $qrUrl = app(QrSessionToken::class)->customerUrl($this->resource, $qrSession['token']);

        return [
            'totem' => [
                'code' => $this->code,
                'name' => $this->name,
                'location' => $this->location,
                'help_contact' => $this->help_contact,
            ],
            'printer' => [
                'state' => $this->displayPrinterState(),
                'reasons' => $this->printer_reasons ?? [],
                'paper_level' => $this->paper_level,
                'markers' => $this->printer_markers ?? [],
                'supports_color' => $this->supports_color,
                'supports_duplex' => $this->supports_duplex,
                'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            ],
            'queue' => $this->visibleQueue(),
            'pricing' => [
                'bw_per_side' => $this->price_bw_per_side,
                'color_per_side' => $this->supports_color ? $this->price_color_per_side : null,
                'max_copies' => $this->max_copies,
                'max_file_mb' => (int) config('totem.max_file_mb'),
                'accepted_extensions' => config('totem.accepted_extensions'),
            ],
            'session_qr' => [
                'url' => $qrUrl,
                'expires_at' => $qrSession['expires_at']->toIso8601String(),
                'svg' => app(QrCodeSvg::class)->render($qrUrl),
            ],
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{pickup_code: string, status: string, progress: array{sheets_done: int, sheets_total: int|null}|null}>
     */
    private function visibleQueue(): array
    {
        return $this->printOrders()
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [PrintOrderStatus::Queued, PrintOrderStatus::Printing])
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', PrintOrderStatus::Ready)
                    ->where('ready_at', '>=', now()->subMinutes((int) config('totem.ready_visible_minutes')))))
            ->orderBy('id')
            ->get()
            ->map(fn (PrintOrder $order): array => [
                'pickup_code' => $order->pickup_code,
                'status' => $order->status->value,
                'progress' => $order->status === PrintOrderStatus::Printing
                    ? ['sheets_done' => $order->sheets_done, 'sheets_total' => $order->totalSheets()]
                    : null,
            ])
            ->values()
            ->all();
    }
}
