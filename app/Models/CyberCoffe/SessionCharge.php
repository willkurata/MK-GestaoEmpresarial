<?php

namespace App\Models\CyberCoffe;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of what was charged on a session (package bought or open time billed).
 */
#[Table('cc_session_charges')]
#[Fillable(['store_id', 'station_session_id', 'plan_id', 'description', 'minutes', 'amount'])]
class SessionCharge extends Model
{
    /** @return BelongsTo<StationSession, $this> */
    public function stationSession(): BelongsTo
    {
        return $this->belongsTo(StationSession::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'minutes' => 'integer'];
    }
}
