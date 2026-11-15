<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'barcode', 'sale_price', 'cost', 'stock_quantity', 'low_stock_threshold', 'status'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Indica se o produto controla estoque.
     */
    public function tracksStock(): bool
    {
        return $this->stock_quantity !== null;
    }

    /**
     * Indica se o estoque está zerado ou no limite de alerta definido.
     */
    public function isLowStock(): bool
    {
        if (! $this->tracksStock()) {
            return false;
        }

        return $this->stock_quantity === 0
            || ($this->low_stock_threshold !== null && $this->stock_quantity <= $this->low_stock_threshold);
    }

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }
}
