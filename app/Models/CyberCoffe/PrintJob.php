<?php

namespace App\Models\CyberCoffe;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ticket queued for the store's local print bridge. "payload" holds the raw
 * ESC/POS bytes encoded in base64; "content" is the plain-text preview.
 *
 * @property CarbonImmutable|null $printed_at
 */
#[Table('cc_print_jobs')]
#[Fillable(['store_id', 'requested_by', 'station_session_id', 'payment_id', 'kind', 'width', 'content', 'payload', 'status', 'printed_at'])]
class PrintJob extends Model
{
    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['width' => 'integer', 'printed_at' => 'datetime'];
    }
}
