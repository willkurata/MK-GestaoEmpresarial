<?php

namespace App\Models\CyberCoffe;

use Carbon\CarbonImmutable;
use Database\Factories\CyberCoffe\StationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property CarbonImmutable|null $last_seen_at
 */
#[Table('cc_stations')]
#[Fillable(['store_id', 'name', 'type', 'status', 'last_seen_at'])]
class Station extends Model
{
    /** @use HasFactory<StationFactory> */
    use HasFactory;

    public const array STATUSES = ['livre', 'ocupada', 'manutencao'];

    public const array TYPES = ['pc', 'console', 'vr', 'notebook', 'outro'];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<StationSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(StationSession::class);
    }

    /** @return HasOne<StationSession, $this> */
    public function activeSession(): HasOne
    {
        return $this->hasOne(StationSession::class)->where('status', 'ativa');
    }

    /** @return MorphMany<ApiToken, $this> */
    public function apiTokens(): MorphMany
    {
        return $this->morphMany(ApiToken::class, 'tokenable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
