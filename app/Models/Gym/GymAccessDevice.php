<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Database\Factories\Gym\GymAccessDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Catraca / terminal facial (or the local edge agent that talks to it), authenticated by its own token.
 *
 * @property CarbonImmutable|null $last_seen_at
 */
#[Fillable(['gym_unit_id', 'name', 'vendor', 'token_hash', 'status', 'last_seen_at'])]
#[Hidden(['token_hash'])]
class GymAccessDevice extends Model
{
    public const VENDORS = ['controlid', 'henry', 'intelbras', 'topdata', 'generico'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ativo'];

    /** @use HasFactory<GymAccessDeviceFactory> */
    use HasFactory;

    /**
     * Replaces the device token and returns the plain value, which is shown only once.
     */
    public function rotateToken(): string
    {
        $plainToken = Str::random(64);
        $this->forceFill(['token_hash' => hash('sha256', $plainToken)])->save();

        return $plainToken;
    }

    /** @return BelongsTo<Gym, $this> */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /** @return BelongsTo<GymUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(GymUnit::class, 'gym_unit_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
