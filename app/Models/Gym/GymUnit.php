<?php

namespace App\Models\Gym;

use Database\Factories\Gym\GymUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address', 'capacity', 'status'])]
class GymUnit extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ativa'];

    /** @use HasFactory<GymUnitFactory> */
    use HasFactory;

    /** @return BelongsTo<Gym, $this> */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /** @return HasMany<GymAccessDevice, $this> */
    public function accessDevices(): HasMany
    {
        return $this->hasMany(GymAccessDevice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }
}
