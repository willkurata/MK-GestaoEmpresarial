<?php

namespace App\Models\CyberCoffe;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Bearer token for the CyberCoffe API. Only the SHA-256 hash is stored.
 */
#[Table('cc_api_tokens')]
#[Fillable(['store_id', 'tokenable_type', 'tokenable_id', 'name', 'token', 'last_used_at', 'expires_at'])]
#[Hidden(['token'])]
class ApiToken extends Model
{
    /**
     * Issue a new token and return its plain-text value (shown only once).
     */
    public static function issue(Attendant|Customer|Station $tokenable, string $name, ?DateTimeInterface $expiresAt): string
    {
        $plainToken = Str::random(48);

        $tokenable->apiTokens()->create([
            'store_id' => $tokenable->store_id,
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return $plainToken;
    }

    public static function findValid(string $plainToken): ?self
    {
        return self::query()
            ->where('token', hash('sha256', $plainToken))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    /** @return MorphTo<Model, $this> */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
