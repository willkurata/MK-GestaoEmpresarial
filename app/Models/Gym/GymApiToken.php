<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $expires_at
 */
#[Fillable(['name', 'token_hash', 'last_used_at', 'expires_at'])]
#[Hidden(['token_hash'])]
class GymApiToken extends Model
{
    /** @return BelongsTo<GymUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(GymUser::class, 'gym_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
