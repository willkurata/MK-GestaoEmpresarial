<?php

namespace App\Models\CyberCoffe;

use Database\Factories\CyberCoffe\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item sold at the counter. Category "produto" covers goods such as water,
 * soda and snacks; category "impressao" is a price per printed page.
 */
#[Table('cc_products')]
#[Fillable(['store_id', 'name', 'category', 'price', 'active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const array CATEGORIES = ['produto', 'impressao'];

    public function isPrinting(): bool
    {
        return $this->category === 'impressao';
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'active' => 'boolean'];
    }
}
