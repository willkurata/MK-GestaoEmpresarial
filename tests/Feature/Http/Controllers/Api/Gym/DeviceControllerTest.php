<?php

use App\Models\Gym\GymAccessDevice;
use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUnit;

/**
 * @return array{0: GymAccessDevice, 1: string}
 */
function turnstileForUnit(GymUnit $unit): array
{
    $device = GymAccessDevice::factory()->create(['gym_unit_id' => $unit->id]);

    return [$device, $device->rotateToken()];
}

function studentWithActivePlan(GymUnit $unit, array $studentAttributes = []): GymStudent
{
    $student = GymStudent::factory()->forUnit($unit)->create($studentAttributes);
    GymSubscription::factory()->create(['gym_student_id' => $student->id]);

    return $student;
}

it('rejects requests without a valid device token', function (?string $token) {
    $request = $token === null ? $this : $this->withToken($token);

    $request->postJson(route('academia.devices.access-check'), [])->assertUnauthorized();
})->with(['missing token' => null, 'unknown token' => 'token-invalido']);

it('rejects an inactive device', function () {
    $device = GymAccessDevice::factory()->inactive()->create();
    $token = $device->rotateToken();

    $this->withToken($token)->postJson(route('academia.devices.heartbeat'))->assertUnauthorized();
});

it('opens the turnstile for an active student with a paid plan and logs the passage', function () {
    $unit = GymUnit::factory()->create();
    [$device, $token] = turnstileForUnit($unit);
    $student = studentWithActivePlan($unit, ['name' => 'Maria Souza']);

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'access_code' => $student->access_code,
        'direction' => 'entrada',
        'method' => 'qrcode',
    ])->assertOk()->assertExactJson(['allowed' => true, 'reason' => null, 'display_name' => 'Maria']);

    $this->assertDatabaseHas('gym_access_events', [
        'gym_id' => $unit->gym_id,
        'gym_unit_id' => $unit->id,
        'gym_access_device_id' => $device->id,
        'gym_student_id' => $student->id,
        'direction' => 'entrada',
        'decision' => 'liberado',
    ]);
    expect($device->refresh()->last_seen_at)->not->toBeNull();
});

it('denies entrance with the reason', function (string $scenario, string $reason) {
    $unit = GymUnit::factory()->create();
    [, $token] = turnstileForUnit($unit);
    $student = match ($scenario) {
        'inactive' => studentWithActivePlan($unit, ['status' => 'inativo']),
        'no plan' => GymStudent::factory()->forUnit($unit)->create(),
        'overdue' => tap(studentWithActivePlan($unit), fn (GymStudent $student) => GymInvoice::factory()->overdueBy(6)->create([
            'gym_subscription_id' => $student->subscriptions()->first()->id,
        ])),
        'face without consent' => studentWithActivePlan($unit, ['face_status' => GymStudent::FACE_ENROLLED]),
    };

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'access_code' => $student->access_code,
        'direction' => 'entrada',
        'method' => $scenario === 'face without consent' ? 'facial' : 'qrcode',
    ])->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('reason', $reason);

    $this->assertDatabaseHas('gym_access_events', ['gym_student_id' => $student->id, 'decision' => 'negado', 'reason' => $reason]);
})->with([
    ['inactive', 'aluno_inativo'],
    ['no plan', 'sem_plano_ativo'],
    ['overdue', 'mensalidade_em_atraso'],
    ['face without consent', 'biometria_nao_autorizada'],
]);

it('still allows entrance while the overdue invoice is within the grace period', function () {
    $unit = GymUnit::factory()->create();
    [, $token] = turnstileForUnit($unit);
    $student = studentWithActivePlan($unit);
    GymInvoice::factory()->overdueBy(5)->create(['gym_subscription_id' => $student->subscriptions()->first()->id]);

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'access_code' => $student->access_code,
        'direction' => 'entrada',
        'method' => 'qrcode',
    ])->assertOk()->assertJsonPath('allowed', true);
});

it('opens by face when the student consented and is enrolled', function () {
    $unit = GymUnit::factory()->create();
    [, $token] = turnstileForUnit($unit);
    $student = studentWithActivePlan($unit, ['face_status' => GymStudent::FACE_ENROLLED]);
    $student->biometricConsents()->create(['gym_id' => $student->gym_id, 'granted_by' => 'titular', 'terms_version' => '2026-11', 'granted_at' => now()]);

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'access_code' => $student->access_code,
        'direction' => 'entrada',
        'method' => 'facial',
    ])->assertOk()->assertJsonPath('allowed', true);
});

it('never blocks a known student from leaving', function () {
    $unit = GymUnit::factory()->create();
    [, $token] = turnstileForUnit($unit);
    $student = GymStudent::factory()->forUnit($unit)->inactive()->create();

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'access_code' => $student->access_code,
        'direction' => 'saida',
        'method' => 'facial',
    ])->assertOk()->assertJsonPath('allowed', true);
});

it('does not recognise a student from another gym', function () {
    $unit = GymUnit::factory()->create();
    [, $token] = turnstileForUnit($unit);
    $outsider = studentWithActivePlan(GymUnit::factory()->create());

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'access_code' => $outsider->access_code,
        'direction' => 'entrada',
        'method' => 'qrcode',
    ])->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('reason', 'aluno_nao_encontrado');

    $this->assertDatabaseHas('gym_access_events', ['gym_id' => $unit->gym_id, 'gym_student_id' => null, 'decision' => 'negado']);
});

it('stores passages decided offline by the edge agent', function () {
    $unit = GymUnit::factory()->create();
    [$device, $token] = turnstileForUnit($unit);
    $student = GymStudent::factory()->forUnit($unit)->create();

    $this->withToken($token)->postJson(route('academia.devices.events'), ['events' => [
        ['access_code' => $student->access_code, 'direction' => 'entrada', 'method' => 'facial', 'decision' => 'liberado', 'occurred_at' => now()->subHour()->toIso8601String()],
        ['access_code' => $student->access_code, 'direction' => 'saida', 'method' => 'facial', 'decision' => 'liberado', 'occurred_at' => now()->subMinutes(5)->toIso8601String()],
    ]])->assertCreated()->assertJsonPath('stored', 2);

    expect(GymAccessEvent::query()->where('gym_access_device_id', $device->id)->where('gym_student_id', $student->id)->count())->toBe(2);
});

it('rejects offline passages dated in the future', function () {
    $unit = GymUnit::factory()->create();
    [, $token] = turnstileForUnit($unit);

    $this->withToken($token)->postJson(route('academia.devices.events'), ['events' => [
        ['access_code' => 'x', 'direction' => 'entrada', 'method' => 'facial', 'decision' => 'liberado', 'occurred_at' => now()->addDay()->toIso8601String()],
    ]])->assertUnprocessable()->assertJsonValidationErrors('events.0.occurred_at');
});

it('syncs to the edge agent only the students allowed in, with their face authorisation', function () {
    $unit = GymUnit::factory()->create();
    [, $token] = turnstileForUnit($unit);
    $allowed = studentWithActivePlan($unit, ['name' => 'Ana Lima']);
    $withoutPlan = GymStudent::factory()->forUnit($unit)->create();
    studentWithActivePlan(GymUnit::factory()->create());

    $response = $this->withToken($token)->getJson(route('academia.devices.sync'));

    $response->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.access_code', $allowed->access_code)
        ->assertJsonPath('students.0.display_name', 'Ana')
        ->assertJsonPath('students.0.face_authorized', false);
    expect($response->json('students.*.access_code'))->not->toContain($withoutPlan->access_code);
});
