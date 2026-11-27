<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a class roll call (chamada): present or absent on a given date.
 *
 * @property CarbonImmutable $attended_on
 * @property bool $present
 */
#[Fillable(['gym_id', 'gym_class_id', 'gym_student_id', 'attended_on', 'present', 'recorded_by_user_id'])]
class GymClassAttendance extends Model
{
    /** @return BelongsTo<GymClass, $this> */
    public function gymClass(): BelongsTo
    {
        return $this->belongsTo(GymClass::class, 'gym_class_id');
    }

    /** @return BelongsTo<GymStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(GymStudent::class, 'gym_student_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attended_on' => 'date',
            'present' => 'boolean',
        ];
    }
}
