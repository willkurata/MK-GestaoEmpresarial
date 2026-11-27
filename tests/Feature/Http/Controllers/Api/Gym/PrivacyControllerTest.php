<?php

use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUser;

it('exports the full personal data of a student to an administrator and audits it', function () {
    $student = GymStudent::factory()->create(['cpf' => '12345678909', 'name' => 'Maria Souza']);
    $admin = GymUser::factory()->for($student->gym)->administrator()->create();

    $this->actingAs($admin, 'gym')->getJson(route('academia.students.personal-data', $student->id))
        ->assertOk()
        ->assertJsonPath('data.student.cpf', '12345678909')
        ->assertJsonPath('data.student.name', 'Maria Souza');

    expect(GymAuditLog::query()->where('action', 'lgpd.dados_exportados')->where('gym_user_id', $admin->id)->exists())->toBeTrue();
});

it('lets a student export their own data', function () {
    $account = GymUser::factory()->student()->create();
    $student = GymStudent::factory()->create(['gym_id' => $account->gym_id, 'cpf' => '12345678909']);
    $student->forceFill(['gym_user_id' => $account->id])->save();

    $this->actingAs($account, 'gym')->getJson(route('academia.me.personal-data'))
        ->assertOk()
        ->assertJsonPath('data.student.id', $student->id)
        ->assertJsonPath('data.student.cpf', '12345678909');
});

it('anonymises a student while keeping the financial history', function () {
    $account = GymUser::factory()->student()->create();
    $student = GymStudent::factory()->faceEnrolled()->create(['gym_id' => $account->gym_id, 'cpf' => '12345678909']);
    $student->forceFill(['gym_user_id' => $account->id])->save();
    $account->issueApiToken('app');
    $subscription = GymSubscription::factory()->create(['gym_student_id' => $student->id]);
    $paid = GymInvoice::factory()->paid()->create(['gym_subscription_id' => $subscription->id]);
    $admin = GymUser::factory()->for($student->gym)->administrator()->create();

    $this->actingAs($admin, 'gym')->postJson(route('academia.students.anonymize', $student->id), ['reason' => 'Pedido do titular'])
        ->assertOk();

    $student->refresh();
    expect($student->name)->toBe('Aluno anonimizado #'.$student->id)
        ->and($student->cpf)->toBeNull()
        ->and($student->cpf_hash)->toBeNull()
        ->and($student->email)->toBeNull()
        ->and($student->birth_date)->toBeNull()
        ->and($student->face_status)->toBe(GymStudent::FACE_NOT_ENROLLED)
        ->and($student->anonymized_at)->not->toBeNull()
        ->and($subscription->refresh()->status)->toBe('cancelada')
        ->and($paid->refresh()->status)->toBe(GymInvoice::STATUS_PAID)
        ->and($account->refresh()->status)->toBe('inativo')
        ->and($account->apiTokens()->count())->toBe(0);
    $this->actingAs($admin, 'gym')->getJson(route('academia.students.show', $student->id))->assertNotFound();
});

it('frees the CPF for a new registration after anonymisation', function () {
    $student = GymStudent::factory()->create(['cpf' => '12345678909']);
    $admin = GymUser::factory()->for($student->gym)->administrator()->create();
    $this->actingAs($admin, 'gym')->postJson(route('academia.students.anonymize', $student->id), ['reason' => 'Pedido do titular'])->assertOk();

    $this->actingAs($admin, 'gym')->postJson(route('academia.students.store'), [
        'unit_id' => $student->gym_unit_id,
        'name' => 'Maria Retorno',
        'cpf' => '12345678909',
        'birth_date' => '1990-01-01',
    ])->assertCreated();
});

it('returns not found when exporting another gym student', function () {
    $student = GymStudent::factory()->create();
    $admin = GymUser::factory()->administrator()->create();

    $this->actingAs($admin, 'gym')->getJson(route('academia.students.personal-data', $student->id))->assertNotFound();
});
