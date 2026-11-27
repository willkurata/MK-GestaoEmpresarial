<?php

use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUnit;
use App\Models\Gym\GymUser;
use Illuminate\Support\Facades\DB;

it('registers a student with the CPF encrypted at rest and masked in the response', function () {
    $unit = GymUnit::factory()->create();
    $manager = GymUser::factory()->forUnit($unit)->manager()->create(['gym_unit_id' => null]);

    $response = $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $unit->id,
        'name' => 'Maria Souza',
        'cpf' => '123.456.789-09',
        'email' => 'Maria@Example.com',
        'birth_date' => '1990-04-12',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.cpf', '***.***.***-09')
        ->assertJsonPath('data.email', 'maria@example.com')
        ->assertJsonPath('data.status', 'ativo')
        ->assertJsonPath('data.face_status', 'nao_cadastrada');
    $student = GymStudent::query()->findOrFail($response->json('data.id'));
    expect($student->gym_id)->toBe($unit->gym_id)
        ->and($student->cpf)->toBe('12345678909')
        ->and(DB::table('gym_students')->where('id', $student->id)->value('cpf'))->not->toContain('12345678909');
});

it('rejects a CPF already registered in the same gym', function () {
    $unit = GymUnit::factory()->create();
    GymStudent::factory()->forUnit($unit)->create(['cpf' => '12345678909']);
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $unit->id,
        'name' => 'Outra Pessoa',
        'cpf' => '123.456.789-09',
        'birth_date' => '1990-04-12',
    ])->assertUnprocessable()->assertJsonValidationErrors(['cpf' => 'Já existe um aluno com este CPF nesta academia.']);
});

it('allows the same CPF in a different gym', function () {
    GymStudent::factory()->create(['cpf' => '12345678909']);
    $unit = GymUnit::factory()->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $unit->id,
        'name' => 'Maria Souza',
        'cpf' => '12345678909',
        'birth_date' => '1990-04-12',
    ])->assertCreated();
});

it('requires a legal guardian for students under 18', function () {
    $unit = GymUnit::factory()->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $unit->id,
        'name' => 'João Menor',
        'cpf' => '98765432100',
        'birth_date' => now()->subYears(15)->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['guardian' => 'Aluno menor de 18 anos exige um responsável legal.']);
});

it('registers a minor together with the guardian', function () {
    $unit = GymUnit::factory()->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $response = $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $unit->id,
        'name' => 'João Menor',
        'cpf' => '98765432100',
        'birth_date' => now()->subYears(15)->toDateString(),
        'guardian' => ['name' => 'Ana Responsável', 'cpf' => '111.222.333-44', 'relationship' => 'mãe'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_minor', true)
        ->assertJsonPath('data.guardians.0.name', 'Ana Responsável')
        ->assertJsonPath('data.guardians.0.cpf', '***.***.***-44');
});

it('validates the required student fields', function () {
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id', 'name', 'cpf', 'birth_date']);
});

it('rejects a unit from another gym when registering a student', function () {
    $otherUnit = GymUnit::factory()->create();
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $otherUnit->id,
        'name' => 'Maria',
        'cpf' => '12345678909',
        'birth_date' => '1990-01-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
});

it('lists only students of the signed-in gym', function () {
    $unit = GymUnit::factory()->create();
    $student = GymStudent::factory()->forUnit($unit)->create();
    GymStudent::factory()->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->getJson(route('academia.students.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $student->id);
});

it('finds a student by CPF without storing it in plain text', function () {
    $unit = GymUnit::factory()->create();
    $student = GymStudent::factory()->forUnit($unit)->create(['cpf' => '12345678909']);
    GymStudent::factory()->forUnit($unit)->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->getJson(route('academia.students.index', ['search' => '123.456.789-09']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $student->id);
});

it('returns not found for a student of another gym', function () {
    $otherStudent = GymStudent::factory()->create();
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.students.show', $otherStudent->id))->assertNotFound();
    $this->actingAs($manager, 'gym')->patchJson(route('academia.students.update', $otherStudent->id), ['status' => 'inativo'])->assertNotFound();
});

it('limits a unit manager to the students of that unit', function () {
    $unit = GymUnit::factory()->create();
    $otherUnit = GymUnit::factory()->create(['gym_id' => $unit->gym_id]);
    $ownStudent = GymStudent::factory()->forUnit($unit)->create();
    $otherUnitStudent = GymStudent::factory()->forUnit($otherUnit)->create();
    $manager = GymUser::factory()->forUnit($unit)->manager()->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.students.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownStudent->id);
    $this->actingAs($manager, 'gym')->getJson(route('academia.students.show', $otherUnitStudent->id))->assertNotFound();
    $this->actingAs($manager, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $otherUnit->id,
        'name' => 'Maria',
        'cpf' => '12345678909',
        'birth_date' => '1990-01-01',
    ])->assertUnprocessable()->assertJsonValidationErrors(['unit_id' => 'Você não gerencia esta unidade.']);
});

it('deactivates a student without deleting the record', function () {
    $unit = GymUnit::factory()->create();
    $student = GymStudent::factory()->forUnit($unit)->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->patchJson(route('academia.students.update', $student->id), ['status' => 'inativo'])
        ->assertOk()
        ->assertJsonPath('data.status', 'inativo');
    expect($student->refresh()->status)->toBe('inativo');
});

it('creates the student app login with the aluno role', function () {
    $unit = GymUnit::factory()->create();
    $student = GymStudent::factory()->forUnit($unit)->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.portal-access', $student->id), [
        'email' => 'aluno@example.com',
        'password' => 'senha-forte-123',
    ])->assertCreated()->assertJsonPath('data.has_portal_access', true);

    $account = $student->refresh()->user;
    expect($account->role)->toBe(GymUser::ROLE_STUDENT)
        ->and($account->gym_id)->toBe($student->gym_id);
});

it('adds a guardian to an existing student', function () {
    $unit = GymUnit::factory()->create();
    $student = GymStudent::factory()->forUnit($unit)->minor()->create();
    $manager = GymUser::factory()->forUnit($unit)->create(['gym_unit_id' => null]);

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.guardians.store', $student->id), [
        'name' => 'Carlos Pai',
        'cpf' => '55566677788',
        'relationship' => 'pai',
    ])->assertCreated()->assertJsonPath('data.name', 'Carlos Pai');

    expect($student->guardians()->first()->cpf)->toBe('55566677788');
});
