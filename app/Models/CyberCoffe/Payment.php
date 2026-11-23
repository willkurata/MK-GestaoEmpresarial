<?php

namespace App\Models\CyberCoffe;

use Carbon\CarbonImmutable;
use Database\Factories\CyberCoffe\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A received payment. Purpose "sessao" pays station time; purpose "creditos"
 * buys prepaid credits. Method "creditos" spends the customer's wallet and is
 * therefore not new revenue.
 *
 * @property CarbonImmutable $paid_at
 */
#[Table('cc_payments')]
#[Fillable(['store_id', 'customer_id', 'station_session_id', 'received_by', 'purpose', 'method', 'amount', 'amount_received', 'change_amount', 'reference', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const array METHODS = ['pix', 'cartao', 'dinheiro', 'creditos'];

    public const array REVENUE_METHODS = ['pix', 'cartao', 'dinheiro'];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<StationSession, $this> */
    public function stationSession(): BelongsTo
    {
        return $this->belongsTo(StationSession::class);
    }

    /** @return BelongsTo<Attendant, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Attendant::class, 'received_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
