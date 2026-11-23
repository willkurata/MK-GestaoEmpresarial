<?php

namespace App\Models\CyberCoffe;

use Database\Factories\CyberCoffe\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sellable time plan: "pacote" is prepaid (fixed minutes for a fixed price);
 * "avulso" is open time billed per started minute at check-out.
 */
#[Table('cc_plans')]
#[Fillable(['store_id', 'name', 'billing', 'price', 'minutes', 'hourly_rate', 'billing_increment_minutes', 'active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    public const array BILLINGS = ['pacote', 'avulso'];

    /** Rounding steps, in minutes, an open plan may bill by. */
    public const array BILLING_INCREMENTS = [1, 5, 10, 15, 30, 60];

    public function isPackage(): bool
    {
        return $this->billing === 'pacote';
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'minutes' => 'integer',
            'billing_increment_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }
}
