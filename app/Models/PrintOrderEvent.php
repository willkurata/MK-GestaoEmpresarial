<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['print_order_id', 'from_status', 'to_status', 'actor', 'details'])]
class PrintOrderEvent extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<PrintOrder, $this> */
    public function printOrder(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
