<?php

namespace App\Models\CyberCoffe;

use Database\Factories\CyberCoffe\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Table('cc_customers')]
#[Fillable(['store_id', 'name', 'phone', 'document', 'password', 'status'])]
#[Hidden(['password', 'document'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->status === 'ativo';
    }

    /**
     * Current prepaid credit balance in cents, computed from the immutable ledger.
     */
    public function creditBalanceInCents(): int
    {
        return (int) round(((float) $this->creditEntries()->sum('amount')) * 100);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<CreditEntry, $this> */
    public function creditEntries(): HasMany
    {
        return $this->hasMany(CreditEntry::class);
    }

    /** @return HasMany<StationSession, $this> */
    public function stationSessions(): HasMany
    {
        return $this->hasMany(StationSession::class);
    }

    /** @return MorphMany<ApiToken, $this> */
    public function apiTokens(): MorphMany
    {
        return $this->morphMany(ApiToken::class, 'tokenable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed', 'document' => 'encrypted'];
    }
}
