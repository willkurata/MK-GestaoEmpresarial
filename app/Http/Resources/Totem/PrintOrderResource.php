<?php

namespace App\Http\Resources\Totem;

use App\Actions\Totem\QrCodeSvg;
use App\Models\PrintOrder;
use App\PrintOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing order (contract shared with the totem front, WIL-47).
 *
 * @mixin PrintOrder
 */
class PrintOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->payment;

        return [
            'id' => $this->public_id,
            'pickup_code' => $this->pickup_code,
            'totem_code' => $this->totem->code,
            'status' => $this->status->value,
            'file' => $this->file_name === null ? null : [
                'name' => $this->file_name,
                'size_bytes' => $this->file_size,
                'mime' => $this->file_mime,
                'page_count' => $this->page_count,
                'converting' => $this->isFileConverting(),
                'thumbnail_url' => null,
            ],
            'options' => [
                'color' => $this->color_mode,
                'duplex' => $this->duplex,
                'pages_per_sheet' => $this->pages_per_sheet,
                'page_range' => $this->page_range,
                'copies' => $this->copies,
            ],
            'quote' => $this->quote_total === null ? null : [
                'pages' => $this->quote_pages,
                'sides' => $this->quote_sides,
                'sheets' => $this->quote_sheets,
                'copies' => $this->copies,
                'per_side' => $this->quote_per_side,
                'total' => $this->quote_total,
            ],
            'payment' => $payment === null ? null : [
                'status' => $payment->status,
                'copy_paste' => $payment->status === 'pending' ? $payment->copy_paste : null,
                'qr_svg' => $payment->status === 'pending' && $this->status === PrintOrderStatus::AwaitingPayment
                    ? app(QrCodeSvg::class)->render($payment->copy_paste)
                    : null,
                'amount' => $payment->amount,
                'expires_at' => $payment->expires_at->toIso8601String(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'refunded_at' => $payment->refunded_at?->toIso8601String(),
            ],
            'progress' => in_array($this->status, [PrintOrderStatus::Printing, PrintOrderStatus::Ready], true) ? [
                'sheets_done' => $this->sheets_done,
                'sheets_total' => $this->totalSheets(),
            ] : null,
            'failure_reason' => $this->failure_reason,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'file_purge_at' => $this->file_purge_at?->toIso8601String(),
        ];
    }
}
