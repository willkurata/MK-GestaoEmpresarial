<?php

namespace App\Models\Gym;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Responsável legal (required for students under 18, LGPD art. 14).
 *
 * @property string $cpf
 */
#[Fillable(['gym_id', 'name', 'cpf', 'relationship', 'phone', 'email'])]
#[Hidden(['cpf'])]
class GymGuardian extends Model
{
    /** @return BelongsTo<GymStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(GymStudent::class, 'gym_student_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cpf' => 'encrypted'];
    }
}
