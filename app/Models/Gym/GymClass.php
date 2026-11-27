<?php

namespace App\Models\Gym;

use Database\Factories\Gym\GymClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turma (e.g. "Pilates terça e quinta 19h"). Enrolment history is kept: leaving a class sets `left_on`.
 *
 * @property array<int, array{weekday: int, starts_at: string, ends_at: string}> $schedule
 * @property int|null $capacity
 */
#[Fillable(['gym_unit_id', 'name', 'modality', 'instructor_name', 'schedule', 'capacity', 'status'])]
class GymClass extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ativa'];

    /** @use HasFactory<GymClassFactory> */
    use HasFactory;

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

    /**
     * Every enrolment, including students who already left the class.
     *
     * @return BelongsToMany<GymStudent, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(GymStudent::class, 'gym_class_students')
            ->withPivot(['id', 'joined_on', 'left_on'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<GymStudent, $this> */
    public function currentStudents(): BelongsToMany
    {
        return $this->students()->wherePivotNull('left_on');
    }

    /** @return HasMany<GymClassAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(GymClassAttendance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'capacity' => 'integer',
        ];
    }
}
