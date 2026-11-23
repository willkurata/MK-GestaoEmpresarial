<?php

namespace App\Models\CyberCoffe;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a session's bill, snapshotted when it was added: time
 * ("tempo": package bought or open time billed), a sold product ("produto")
 * or printed pages ("impressao", quantity = pages).
 */
#[Table('cc_session_charges')]
#[Fillable(['store_id', 'station_session_id', 'plan_id', 'kind', 'product_id', 'created_by', 'description', 'minutes', 'quantity', 'unit_price', 'manual_price', 'external_reference', 'amount'])]
class SessionCharge extends Model
{
    public const array KINDS = ['tempo', 'produto', 'impressao'];

    public function isTime(): bool
    {
        return $this->kind === 'tempo';
    }

    public function amountInCents(): int
    {
        return (int) round(((float) $this->amount) * 100);
    }

    /** @return BelongsTo<StationSession, $this> */
    public function stationSession(): BelongsTo
    {
        return $this->belongsTo(StationSession::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Attendant, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Attendant::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'minutes' => 'integer',
            'quantity' => 'integer',
            'manual_price' => 'boolean',
        ];
    }
}
