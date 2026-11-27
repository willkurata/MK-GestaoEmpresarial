<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Turnstile passage attempt (entrada/saída), allowed or denied, kept as history.
 *
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['gym_id', 'gym_unit_id', 'gym_access_device_id', 'gym_student_id', 'direction', 'method', 'decision', 'reason', 'recorded_by_user_id', 'occurred_at'])]
class GymAccessEvent extends Model
{
    public const DIRECTIONS = ['entrada', 'saida'];

    /**
     * Identification methods a turnstile can report. "codigo" is the keypad fallback (matrícula + PIN).
     */
    public const DEVICE_METHODS = ['facial', 'qrcode', 'cartao', 'codigo'];

    public const METHODS = [...self::DEVICE_METHODS, 'manual'];

    public const ALLOWED = 'liberado';

    public const DENIED = 'negado';

    /** @return BelongsTo<GymStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(GymStudent::class, 'gym_student_id');
    }

    /** @return BelongsTo<GymUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(GymUnit::class, 'gym_unit_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
