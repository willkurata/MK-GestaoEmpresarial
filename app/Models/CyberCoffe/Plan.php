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
#[Fillable(['store_id', 'name', 'billing', 'price', 'minutes', 'hourly_rate', 'active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    public const array BILLINGS = ['pacote', 'avulso'];

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
            'active' => 'boolean',
        ];
    }
}
