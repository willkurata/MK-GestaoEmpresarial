<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PrintPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $print_order_id
 * @property string $provider
 * @property string $external_id
 * @property string $amount
 * @property string $status
 * @property string $copy_paste
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $refunded_at
 */
#[Fillable(['print_order_id', 'provider', 'external_id', 'amount', 'status', 'copy_paste', 'expires_at', 'paid_at', 'refunded_at'])]
class PrintPayment extends Model
{
    /** @use HasFactory<PrintPaymentFactory> */
    use HasFactory;

    /** @return BelongsTo<PrintOrder, $this> */
    public function printOrder(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
