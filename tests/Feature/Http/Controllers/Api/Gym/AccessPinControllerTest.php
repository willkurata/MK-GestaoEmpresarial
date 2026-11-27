<?php

use App\Models\Gym\GymAccessDevice;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUnit;
use App\Models\Gym\GymUser;
use Illuminate\Support\Facades\Hash;

/**
 * @return array{0: GymStudent, 1: string}
 */
function keypadSetup(string $pin = '4821'): array
{
    $unit = GymUnit::factory()->create();
    $student = GymStudent::factory()->forUnit($unit)->create(['name' => 'Joana Lima']);
    GymSubscription::factory()->create(['gym_student_id' => $student->id]);
    $student->setAccessPin($pin);
    $device = GymAccessDevice::factory()->create(['gym_unit_id' => $unit->id]);

    return [$student->refresh(), $device->rotateToken()];
}

it('gives every new student a six-digit registration number', function () {
    $student = GymStudent::factory()->create();

    expect($student->registration_number)->toMatch('/^\d{6}$/');
});

it('opens the turnstile with registration number and PIN when the face cannot be read', function () {
    [$student, $token] = keypadSetup();

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'method' => 'codigo',
        'direction' => 'entrada',
        'registration_number' => $student->registration_number,
        'pin' => '4821',
    ])->assertOk()->assertExactJson(['allowed' => true, 'reason' => null, 'display_name' => 'Joana']);

    $this->assertDatabaseHas('gym_access_events', ['gym_student_id' => $student->id, 'method' => 'codigo', 'decision' => 'liberado']);
});

it('still applies the financial rules to the keypad fallback', function () {
    [$student, $token] = keypadSetup();
    GymInvoice::factory()->overdueBy(60)->create(['gym_subscription_id' => $student->subscriptions()->value('id')]);

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'method' => 'codigo',
        'direction' => 'entrada',
        'registration_number' => $student->registration_number,
        'pin' => '4821',
    ])->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('reason', 'mensalidade_em_atraso');
});

it('denies a wrong PIN without revealing the student name', function () {
    [$student, $token] = keypadSetup();

    $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'method' => 'codigo',
        'direction' => 'entrada',
        'registration_number' => $student->registration_number,
        'pin' => '0000',
    ])->assertOk()->assertExactJson(['allowed' => false, 'reason' => 'senha_invalida', 'display_name' => null]);

    expect($student->refresh()->access_pin_failed_attempts)->toBe(1);
});

it('locks the keypad fallback after five wrong PINs, even for the right PIN', function () {
    [$student, $token] = keypadSetup();
    $attempt = fn (string $pin) => $this->withToken($token)->postJson(route('academia.devices.access-check'), [
        'method' => 'codigo',
        'direction' => 'entrada',
        'registration_number' => $student->registration_number,
        'pin' => $pin,
    ]);

    foreach (range(1, 4) as $ignored) {
        $attempt('0000')->assertJsonPath('reason', 'senha_invalida');
    }
    $attempt('0000')->assertJsonPath('reason', 'senha_bloqueada');
    $attempt('4821')->assertJsonPath('allowed', false)->assertJsonPath('reason', 'senha_bloqueada');
});

it('requires registration number and PIN for the keypad method', function () {
    [, $token] = keypadSetup();

    $this->withToken($token)->postJson(route('academia.devices.access-check'), ['method' => 'codigo', 'direction' => 'entrada'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['registration_number', 'pin'])
        ->assertJsonMissingValidationErrors('access_code');
});

it('lets staff set a PIN, which unlocks a blocked fallback and is audited', function () {
    [$student] = keypadSetup();
    $student->forceFill(['access_pin_failed_attempts' => 5, 'access_pin_locked_at' => now()])->save();
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->putJson(route('academia.students.access-pin', $student), ['pin' => '135790', 'pin_confirmation' => '135790'])
        ->assertOk()
        ->assertJsonPath('data.has_access_pin', true)
        ->assertJsonPath('data.access_pin_locked', false)
        ->assertJsonMissingPath('data.access_pin');

    $student->refresh();
    expect(Hash::check('135790', $student->access_pin))->toBeTrue()
        ->and($student->access_pin_failed_attempts)->toBe(0)
        ->and(GymAuditLog::query()->where('action', 'catraca.senha_definida')->exists())->toBeTrue();
});

it('rejects a PIN that is not 4 to 6 digits', function (string $pin) {
    $student = GymStudent::factory()->create();
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->putJson(route('academia.students.access-pin', $student), ['pin' => $pin, 'pin_confirmation' => $pin])
        ->assertUnprocessable()->assertJsonValidationErrors('pin');
})->with(['too short' => '123', 'too long' => '1234567', 'letters' => '12ab']);

it('does not let staff set the PIN of another gym student', function () {
    $student = GymStudent::factory()->create();
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->putJson(route('academia.students.access-pin', $student), ['pin' => '1234', 'pin_confirmation' => '1234'])
        ->assertNotFound();
});

it('lets the student change their own PIN after confirming the account password', function () {
    $account = GymUser::factory()->student()->create();
    $student = GymStudent::factory()->create(['gym_id' => $account->gym_id, 'gym_user_id' => $account->id]);

    $this->actingAs($account, 'gym')->putJson(route('academia.me.access-pin'), ['password' => 'errada', 'pin' => '2468', 'pin_confirmation' => '2468'])
        ->assertUnprocessable()->assertJsonValidationErrors(['password' => 'Senha da conta incorreta.']);
    expect($student->refresh()->access_pin)->toBeNull();

    $this->actingAs($account, 'gym')->putJson(route('academia.me.access-pin'), ['password' => 'password', 'pin' => '2468', 'pin_confirmation' => '2468'])
        ->assertOk()->assertJsonPath('data.has_access_pin', true);
    expect(Hash::check('2468', $student->refresh()->access_pin))->toBeTrue();
});
