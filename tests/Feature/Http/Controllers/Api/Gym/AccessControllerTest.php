<?php

use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUnit;
use App\Models\Gym\GymUser;

function passage(GymStudent $student, string $direction, string $decision = 'liberado', ?string $at = null): GymAccessEvent
{
    return GymAccessEvent::create([
        'gym_id' => $student->gym_id,
        'gym_unit_id' => $student->gym_unit_id,
        'gym_student_id' => $student->id,
        'direction' => $direction,
        'method' => 'facial',
        'decision' => $decision,
        'occurred_at' => $at ?? now(),
    ]);
}

it('counts as present only students whose last allowed passage today was an entrance', function () {
    $this->travelTo('2026-11-23 18:00:00');
    $unit = GymUnit::factory()->create(['capacity' => 50]);
    $inside = GymStudent::factory()->forUnit($unit)->create();
    $left = GymStudent::factory()->forUnit($unit)->create();
    $denied = GymStudent::factory()->forUnit($unit)->create();
    $yesterday = GymStudent::factory()->forUnit($unit)->create();
    passage($inside, 'entrada', at: '2026-11-23 17:00:00');
    passage($left, 'entrada', at: '2026-11-23 16:00:00');
    passage($left, 'saida', at: '2026-11-23 17:30:00');
    passage($denied, 'entrada', 'negado');
    passage($yesterday, 'entrada', at: '2026-11-22 20:00:00');
    $manager = GymUser::factory()->for($unit->gym)->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.access.occupancy'))
        ->assertOk()
        ->assertJsonPath('data.0.unit_id', $unit->id)
        ->assertJsonPath('data.0.present', 1)
        ->assertJsonPath('data.0.capacity', 50);
});

it('releases the turnstile manually with a reason and audits it', function () {
    $unit = GymUnit::factory()->create();
    $student = GymStudent::factory()->forUnit($unit)->create();
    $manager = GymUser::factory()->for($unit->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.access.manual-release'), [
        'unit_id' => $unit->id,
        'student_id' => $student->id,
        'direction' => 'entrada',
        'reason' => 'Leitor facial em manutenção',
    ])->assertCreated()->assertJsonPath('data.method', 'manual')->assertJsonPath('data.recorded_by_user_id', $manager->id);

    expect(GymAuditLog::query()->where('action', 'catraca.liberacao_manual')->where('gym_user_id', $manager->id)->exists())->toBeTrue();
});

it('requires a reason for manual release', function () {
    $unit = GymUnit::factory()->create();
    $manager = GymUser::factory()->for($unit->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.access.manual-release'), ['unit_id' => $unit->id, 'direction' => 'entrada'])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('does not let a unit manager release another unit turnstile', function () {
    $unit = GymUnit::factory()->create();
    $otherUnit = GymUnit::factory()->create(['gym_id' => $unit->gym_id]);
    $manager = GymUser::factory()->forUnit($unit)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.access.manual-release'), [
        'unit_id' => $otherUnit->id,
        'direction' => 'entrada',
        'reason' => 'Teste',
    ])->assertUnprocessable()->assertJsonValidationErrors(['unit_id' => 'Você não gerencia esta unidade.']);
});

it('lists only the passages of the signed-in gym', function () {
    $student = GymStudent::factory()->create();
    $event = passage($student, 'entrada');
    passage(GymStudent::factory()->create(), 'entrada');
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.access.events'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $event->id);
});

it('registers a turnstile and shows its token only once', function () {
    $unit = GymUnit::factory()->create();
    $admin = GymUser::factory()->for($unit->gym)->administrator()->create();

    $response = $this->actingAs($admin, 'gym')->postJson(route('academia.access-devices.store'), [
        'unit_id' => $unit->id,
        'name' => 'Catraca recepção',
        'vendor' => 'controlid',
    ]);

    $response->assertCreated()->assertJsonMissingPath('data.token_hash');
    $token = $response->json('device_token');
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->postJson(route('academia.devices.heartbeat'))->assertOk();
});
