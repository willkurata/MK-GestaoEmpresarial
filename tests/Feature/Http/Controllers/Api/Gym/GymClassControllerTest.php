<?php

use App\Models\Gym\GymClass;
use App\Models\Gym\GymClassAttendance;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUnit;
use App\Models\Gym\GymUser;

function classWithStudents(int $students = 2, array $classAttributes = []): GymClass
{
    $unit = GymUnit::factory()->create();
    $class = GymClass::factory()->forUnit($unit)->create($classAttributes);
    GymStudent::factory()->forUnit($unit)->count($students)->create()
        ->each(fn (GymStudent $student) => $class->students()->attach($student->id, ['joined_on' => '2026-11-01']));

    return $class;
}

it('creates a class with its weekly schedule', function () {
    $unit = GymUnit::factory()->create();
    $manager = GymUser::factory()->for($unit->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.store'), [
        'unit_id' => $unit->id,
        'name' => 'Pilates noite',
        'modality' => 'Pilates',
        'schedule' => [['weekday' => 2, 'starts_at' => '19:00', 'ends_at' => '20:00']],
        'capacity' => 12,
    ])->assertCreated()->assertJsonPath('data.name', 'Pilates noite')->assertJsonPath('data.enrolled_count', 0);

    $this->assertDatabaseHas('gym_classes', ['gym_id' => $unit->gym_id, 'gym_unit_id' => $unit->id, 'name' => 'Pilates noite', 'capacity' => 12]);
});

it('validates the class schedule', function () {
    $unit = GymUnit::factory()->create();
    $manager = GymUser::factory()->for($unit->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.store'), [
        'unit_id' => $unit->id,
        'name' => 'Spinning',
        'schedule' => [['weekday' => 7, 'starts_at' => '20:00', 'ends_at' => '19:00']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['schedule.0.weekday', 'schedule.0.ends_at']);
});

it('does not let a unit manager create a class in another unit', function () {
    $unit = GymUnit::factory()->create();
    $otherUnit = GymUnit::factory()->create(['gym_id' => $unit->gym_id]);
    $manager = GymUser::factory()->forUnit($unit)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.store'), [
        'unit_id' => $otherUnit->id,
        'name' => 'Yoga',
        'schedule' => [['weekday' => 1, 'starts_at' => '07:00', 'ends_at' => '08:00']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['unit_id' => 'Você não gerencia esta unidade.']);
});

it('hides classes of another gym', function () {
    $class = GymClass::factory()->create();
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.classes.show', $class->id))->assertNotFound();
    $this->actingAs($manager, 'gym')->getJson(route('academia.classes.index'))->assertOk()->assertJsonCount(0, 'data');
});

it('does not let students manage classes', function () {
    $account = GymUser::factory()->student()->create();

    $this->actingAs($account, 'gym')->getJson(route('academia.classes.index'))->assertForbidden();
});

it('enrolls a student and refuses duplicates and a full class', function () {
    $class = classWithStudents(1, ['capacity' => 2]);
    $student = GymStudent::factory()->forUnit($class->unit)->create();
    $extra = GymStudent::factory()->forUnit($class->unit)->create();
    $manager = GymUser::factory()->for($class->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.students.store', $class->id), ['student_id' => $student->id])
        ->assertOk()->assertJsonPath('data.enrolled_count', 2);
    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.students.store', $class->id), ['student_id' => $student->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['student_id' => 'O aluno já está nesta turma.']);
    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.students.store', $class->id), ['student_id' => $extra->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['student_id' => 'A turma está lotada.']);
});

it('refuses to enroll a student of another gym', function () {
    $class = classWithStudents(0);
    $manager = GymUser::factory()->for($class->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.students.store', $class->id), ['student_id' => GymStudent::factory()->create()->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['student_id' => 'Aluno não encontrado.']);
});

it('keeps the enrolment history when a student leaves the class', function () {
    $this->travelTo('2026-11-24 10:00:00');
    $class = classWithStudents(1);
    $student = $class->students()->first();
    $manager = GymUser::factory()->for($class->gym)->create();

    $this->actingAs($manager, 'gym')->deleteJson(route('academia.classes.students.destroy', [$class->id, $student->id]))
        ->assertOk()
        ->assertJsonPath('data.enrolled_count', 0)
        ->assertJsonPath('data.students.0.left_on', '2026-11-24');

    $this->actingAs($manager, 'gym')->deleteJson(route('academia.classes.students.destroy', [$class->id, $student->id]))->assertNotFound();
});

it('records a roll call and corrects it when sent again for the same date', function () {
    $class = classWithStudents(2);
    [$first, $second] = $class->students()->orderBy('gym_students.id')->get()->all();
    $manager = GymUser::factory()->for($class->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.attendances.store', $class->id), [
        'date' => '2026-11-18',
        'present_student_ids' => [$first->id],
    ])->assertCreated()->assertJsonPath('data.present', 1)->assertJsonPath('data.absent', 1);

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.attendances.store', $class->id), [
        'date' => '2026-11-18',
        'present_student_ids' => [$first->id, $second->id],
    ])->assertCreated()->assertJsonPath('data.present', 2)->assertJsonPath('data.absent', 0);

    expect(GymClassAttendance::query()->count())->toBe(2)
        ->and(GymClassAttendance::query()->where('recorded_by_user_id', $manager->id)->where('present', true)->count())->toBe(2);
});

it('refuses a roll call with a student who was not in the class', function () {
    $class = classWithStudents(1);
    $outsider = GymStudent::factory()->forUnit($class->unit)->create();
    $manager = GymUser::factory()->for($class->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.classes.attendances.store', $class->id), [
        'date' => '2026-11-18',
        'present_student_ids' => [$outsider->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['present_student_ids' => 'Há alunos que não pertenciam à turma nesta data.']);
});
