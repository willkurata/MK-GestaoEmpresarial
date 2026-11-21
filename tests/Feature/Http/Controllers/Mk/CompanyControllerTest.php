<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('generates a one-time temporary password for an administrator', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->administrator()->create();

    $response = $this->actingAs($superAdmin)
        ->from(route('mk.companies.show', $company))
        ->post(route('mk.companies.users.reset-password', [$company, $companyUser]));

    $response->assertRedirect(route('mk.companies.show', $company));
    $temporaryPassword = session('temporaryPassword.password');
    expect($temporaryPassword)->toBeString()->toHaveLength(18);
    $this->assertDatabaseHas('company_users', [
        'id' => $companyUser->id,
        'company_id' => $company->id,
        'must_change_password' => true,
    ]);
    expect(Hash::check($temporaryPassword, $companyUser->fresh()->password))->toBeTrue();

    $this->get(route('mk.companies.show', $company))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.temporaryPassword.username', $companyUser->username)
            ->where('flash.temporaryPassword.password', $temporaryPassword));

    $this->get(route('mk.companies.show', $company))
        ->assertInertia(fn (Assert $page) => $page->where('flash.temporaryPassword', null));
});

it('does not reset an administrator account from another company', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($otherCompany)->administrator()->create();
    $originalPasswordHash = $companyUser->password;

    $response = $this->actingAs($superAdmin)
        ->post(route('mk.companies.users.reset-password', [$company, $companyUser]));

    $response->assertNotFound();
    expect($companyUser->fresh()->password)->toBe($originalPasswordHash);
    expect($companyUser->fresh()->must_change_password)->toBeFalse();
});

it('does not offer password reset for a non-administrator account', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $originalPasswordHash = $companyUser->password;

    $response = $this->actingAs($superAdmin)
        ->post(route('mk.companies.users.reset-password', [$company, $companyUser]));

    $response->assertNotFound();
    expect($companyUser->fresh()->password)->toBe($originalPasswordHash);
    expect($companyUser->fresh()->must_change_password)->toBeFalse();
});

it('requires an administrator to change a temporary password before opening the workspace', function () {
    $company = Company::factory()->create();
    $temporaryPassword = 'MKTemporaryPassword123';
    $companyUser = CompanyUser::factory()->for($company)->administrator()->create([
        'password' => $temporaryPassword,
        'must_change_password' => true,
    ]);

    $loginResponse = $this->actingAs($company, 'company')
        ->post(route('company.user.login'), [
            'username' => $companyUser->username,
            'password' => $temporaryPassword,
        ]);

    $loginResponse->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('company.user.security.edit'));
    $this->get(route('company.user.security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/Security')
            ->where('mustChangePassword', true));

    $this->put(route('company.user.password.update'), [
        'current_password' => $temporaryPassword,
        'password' => 'MyPersonalPassword123!',
        'password_confirmation' => 'MyPersonalPassword123!',
    ])->assertRedirect();

    expect($companyUser->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('MyPersonalPassword123!', $companyUser->fresh()->password))->toBeTrue();
    $this->get(route('dashboard'))->assertOk();
});

it('audits a password reset issued by the MK team', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->administrator()->create();

    $this->actingAs($superAdmin)
        ->post(route('mk.companies.users.reset-password', [$company, $companyUser]))
        ->assertRedirect();

    $log = AuditLog::query()->where('action', 'company_user.password_reset')->sole();
    expect($log)
        ->company_id->toBe($company->id)
        ->actor_type->toBe($superAdmin->getMorphClass())
        ->actor_id->toBe($superAdmin->id)
        ->subject_id->toBe($companyUser->id)
        ->metadata->toBe(['source' => 'mk', 'username' => $companyUser->username]);
});

it('keeps at least one active administrator when the MK team edits a user', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();
    $administrator = CompanyUser::factory()->for($company)->administrator()->create();

    $this->actingAs($superAdmin)
        ->patch(route('mk.companies.users.update', [$company, $administrator]), [
            'role' => 'Administrador',
            'status' => 'inativa',
        ])
        ->assertSessionHasErrors(['role' => 'A empresa precisa manter pelo menos um Administrador ativo.']);

    expect($administrator->fresh()->status)->toBe('ativa');
    expect(AuditLog::query()->count())->toBe(0);
});

it('audits user access changes made by the MK team', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();
    CompanyUser::factory()->for($company)->administrator()->create();
    $employee = CompanyUser::factory()->for($company)->create();

    $this->actingAs($superAdmin)
        ->patch(route('mk.companies.users.update', [$company, $employee]), [
            'role' => 'Moderador',
            'status' => 'ativa',
        ])
        ->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('action', 'company_user.updated')->sole()->metadata)->toBe([
        'source' => 'mk',
        'changes' => ['role' => ['from' => 'Funcionario', 'to' => 'Moderador']],
    ]);
});
