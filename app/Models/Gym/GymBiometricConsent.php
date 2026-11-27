<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Specific, highlighted consent for facial biometrics (LGPD art. 11). Revocation keeps the record for accountability.
 *
 * @property CarbonImmutable $granted_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['gym_id', 'gym_guardian_id', 'granted_by', 'terms_version', 'recorded_by_user_id', 'ip_address', 'granted_at', 'revoked_at'])]
class GymBiometricConsent extends Model
{
    public const GRANTED_BY_HOLDER = 'titular';

    public const GRANTED_BY_GUARDIAN = 'responsavel';

    /** @return BelongsTo<GymStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(GymStudent::class, 'gym_student_id');
    }

    /** @return BelongsTo<GymGuardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GymGuardian::class, 'gym_guardian_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
