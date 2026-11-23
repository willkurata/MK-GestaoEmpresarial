<?php

namespace App\Models\CyberCoffe;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable credit ledger line: positive for purchases, negative for usage.
 */
#[Table('cc_credit_entries')]
#[Fillable(['store_id', 'customer_id', 'payment_id', 'created_by', 'type', 'amount', 'description'])]
class CreditEntry extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
