<?php

use App\Models\Gym\GymAccessDevice;
use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymUser;

function grantFaceConsent(GymStudent $student): void
{
    $student->biometricConsents()->create(['gym_id' => $student->gym_id, 'granted_by' => 'titular', 'terms_version' => '2026-11', 'granted_at' => now()]);
}

it('records the specific biometric consent of an adult student and audits it', function () {
    $student = GymStudent::factory()->create();
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.biometric-consent.store', $student->id), [
        'granted_by' => 'titular',
        'terms_version' => '2026-11',
        'accepted' => true,
    ])->assertCreated()->assertJsonPath('data.granted_by', 'titular');

    $this->assertDatabaseHas('gym_biometric_consents', ['gym_student_id' => $student->id, 'recorded_by_user_id' => $manager->id, 'revoked_at' => null]);
    expect(GymAuditLog::query()->where('action', 'biometria.consentimento_concedido')->where('subject_id', $student->id)->exists())->toBeTrue();
});

it('requires explicit acceptance and the current terms version', function () {
    $student = GymStudent::factory()->create();
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.biometric-consent.store', $student->id), [
        'granted_by' => 'titular',
        'terms_version' => '2025-01',
        'accepted' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'terms_version' => 'Os termos de biometria foram atualizados. Apresente a versão vigente ao aluno.',
        'accepted' => 'O consentimento específico para biometria precisa ser aceito.',
    ]);
});

it('requires the guardian to consent for a minor', function () {
    $student = GymStudent::factory()->minor()->create();
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.biometric-consent.store', $student->id), [
        'granted_by' => 'titular',
        'terms_version' => '2026-11',
        'accepted' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['granted_by' => 'Para menores de 18 anos o consentimento deve ser dado pelo responsável legal.']);
});

it('accepts the consent of the guardian of a minor', function () {
    $student = GymStudent::factory()->minor()->create();
    $guardian = $student->guardians()->create(['gym_id' => $student->gym_id, 'name' => 'Ana', 'cpf' => '11122233344', 'relationship' => 'mãe']);
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.biometric-consent.store', $student->id), [
        'granted_by' => 'responsavel',
        'guardian_id' => $guardian->id,
        'terms_version' => '2026-11',
        'accepted' => true,
    ])->assertCreated();

    $this->assertDatabaseHas('gym_biometric_consents', ['gym_student_id' => $student->id, 'gym_guardian_id' => $guardian->id]);
});

it('refuses face enrolment without consent', function () {
    $student = GymStudent::factory()->create();
    $device = GymAccessDevice::factory()->create(['gym_unit_id' => $student->gym_unit_id]);
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.face-enrollment.store', $student->id), ['device_id' => $device->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['student' => 'Registre o consentimento de biometria antes do cadastro facial.']);
});

it('never accepts face images or templates in the cloud', function (string $field) {
    $student = GymStudent::factory()->create();
    grantFaceConsent($student);
    $device = GymAccessDevice::factory()->create(['gym_unit_id' => $student->gym_unit_id]);
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.face-enrollment.store', $student->id), [
        'device_id' => $device->id,
        $field => 'base64-da-imagem',
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($student->refresh()->face_status)->toBe(GymStudent::FACE_NOT_ENROLLED);
})->with(['photo', 'image', 'template']);

it('marks the face as enrolled once consent exists', function () {
    $student = GymStudent::factory()->create();
    grantFaceConsent($student);
    $device = GymAccessDevice::factory()->create(['gym_unit_id' => $student->gym_unit_id]);
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.face-enrollment.store', $student->id), ['device_id' => $device->id])
        ->assertOk()
        ->assertJsonPath('data.face_status', 'cadastrada')
        ->assertJsonPath('data.has_biometric_consent', true);
});

it('revoking consent clears the face enrolment but keeps the consent history', function () {
    $student = GymStudent::factory()->faceEnrolled()->create();
    grantFaceConsent($student);
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->deleteJson(route('academia.students.biometric-consent.destroy', $student->id))
        ->assertOk()
        ->assertJsonPath('data.face_status', 'nao_cadastrada')
        ->assertJsonPath('data.has_biometric_consent', false);

    expect($student->biometricConsents()->sole()->revoked_at)->not->toBeNull();
});

it('lets the student withdraw their own biometric consent', function () {
    $account = GymUser::factory()->student()->create();
    $student = GymStudent::factory()->faceEnrolled()->create(['gym_id' => $account->gym_id]);
    $student->forceFill(['gym_user_id' => $account->id])->save();
    grantFaceConsent($student);

    $this->actingAs($account, 'gym')->deleteJson(route('academia.me.biometric-consent.revoke'))
        ->assertOk()
        ->assertJsonPath('data.face_status', 'nao_cadastrada');

    expect(GymAuditLog::query()->where('action', 'biometria.consentimento_revogado_pelo_titular')->exists())->toBeTrue();
});
