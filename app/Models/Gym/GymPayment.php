<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment of an invoice. Refunds never delete the payment: they mark it as `estornado` with reason and author.
 *
 * @property string $amount
 * @property CarbonImmutable $paid_at
 * @property CarbonImmutable|null $refunded_at
 */
#[Fillable(['gym_id', 'method', 'amount', 'status', 'source', 'gateway_payment_id', 'recorded_by_user_id', 'paid_at', 'refunded_at', 'refund_reason', 'refunded_by_user_id'])]
class GymPayment extends Model
{
    public const METHODS = ['pix', 'cartao', 'boleto', 'dinheiro'];

    public const STATUS_CONFIRMED = 'confirmado';

    public const STATUS_REFUNDED = 'estornado';

    /** @return BelongsTo<GymInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GymInvoice::class, 'gym_invoice_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
