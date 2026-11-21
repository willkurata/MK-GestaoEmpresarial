<?php

use App\Actions\CompanyUsers\ResetCompanyUserPassword;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function signInCompanyAdministrator(Company $company): CompanyUser
{
    $administrator = CompanyUser::factory()->for($company)->administrator()->create();
    test()->actingAs($company, 'company')->actingAs($administrator, 'company_user');

    return $administrator;
}

it('lists only the users of the signed-in company', function () {
    $company = Company::factory()->create();
    $administrator = signInCompanyAdministrator($company);
    $administrator->update(['name' => 'Zélia Administradora']);
    $employee = CompanyUser::factory()->for($company)->create(['name' => 'Bruna Caixa']);
    CompanyUser::factory()->for(Company::factory())->create();

    $response = $this->get(route('company.users.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('company/Users/Index')
        ->has('users', 2)
        ->where('users.0.id', $employee->id)
        ->where('currentUserId', $administrator->id)
        ->missing('users.0.password'));
});

it('refuses user management to moderators and employees', function (string $role) {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create(['role' => $role]);
    $target = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $this->get(route('company.users.index'))->assertForbidden();
    $this->post(route('company.users.reset-password', $target))->assertForbidden();

    expect(Hash::check('password', $target->fresh()->password))->toBeTrue();
})->with(['Moderador', 'Funcionario']);

it('creates an employee for the signed-in company and audits it', function () {
    $company = Company::factory()->create();
    $administrator = signInCompanyAdministrator($company);

    $response = $this->post(route('company.users.store'), [
        'name' => ' Carla Operadora ',
        'cpf' => '123.456.789-01',
        'username' => ' carla ',
        'password' => 'SenhaInicial123!',
        'password_confirmation' => 'SenhaInicial123!',
        'role' => 'Funcionario',
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $created = CompanyUser::query()->where('username', 'carla')->sole();
    expect($created)
        ->company_id->toBe($company->id)
        ->name->toBe('Carla Operadora')
        ->cpf->toBe('12345678901')
        ->role->toBe('Funcionario')
        ->status->toBe('ativa');
    expect(Hash::check('SenhaInicial123!', $created->password))->toBeTrue();

    $log = AuditLog::query()->where('action', 'company_user.created')->sole();
    expect($log)
        ->company_id->toBe($company->id)
        ->actor_id->toBe($administrator->id)
        ->subject_id->toBe($created->id)
        ->metadata->toBe(['username' => 'carla', 'role' => 'Funcionario']);
});

it('allows the same cpf and username in another company but not twice in the same company', function () {
    $company = Company::factory()->create();
    signInCompanyAdministrator($company);
    CompanyUser::factory()->for(Company::factory())->create(['cpf' => '11122233344', 'username' => 'joana']);
    $payload = [
        'name' => 'Joana',
        'cpf' => '11122233344',
        'username' => 'joana',
        'password' => 'SenhaInicial123!',
        'password_confirmation' => 'SenhaInicial123!',
        'role' => 'Funcionario',
    ];

    $this->post(route('company.users.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('company.users.store'), $payload)->assertSessionHasErrors(['cpf', 'username']);

    expect($company->companyUsers()->where('username', 'joana')->count())->toBe(1);
});

it('rejects an unknown role when creating a user', function () {
    $company = Company::factory()->create();
    signInCompanyAdministrator($company);

    $this->post(route('company.users.store'), [
        'name' => 'Pessoa',
        'cpf' => '99988877766',
        'username' => 'pessoa',
        'password' => 'SenhaInicial123!',
        'password_confirmation' => 'SenhaInicial123!',
        'role' => 'SuperAdmin',
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('company_users', ['username' => 'pessoa']);
});

it('ignores a company id sent in the payload when creating a user', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    signInCompanyAdministrator($company);

    $this->post(route('company.users.store'), [
        'company_id' => $otherCompany->id,
        'name' => 'Pessoa',
        'cpf' => '99988877766',
        'username' => 'pessoa',
        'password' => 'SenhaInicial123!',
        'password_confirmation' => 'SenhaInicial123!',
        'role' => 'Funcionario',
    ])->assertSessionHasNoErrors();

    expect(CompanyUser::query()->where('username', 'pessoa')->sole()->company_id)->toBe($company->id);
});

it('updates and deactivates an employee and audits the changes', function () {
    $company = Company::factory()->create();
    $administrator = signInCompanyAdministrator($company);
    $employee = CompanyUser::factory()->for($company)->create(['name' => 'Antigo', 'username' => 'antigo']);

    $response = $this->patch(route('company.users.update', $employee), [
        'name' => 'Novo Nome',
        'username' => 'novo',
        'role' => 'Moderador',
        'status' => 'inativa',
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    expect($employee->fresh())
        ->name->toBe('Novo Nome')
        ->username->toBe('novo')
        ->role->toBe('Moderador')
        ->status->toBe('inativa');
    $log = AuditLog::query()->where('action', 'company_user.updated')->sole();
    expect($log->actor_id)->toBe($administrator->id)
        ->and($log->metadata['changes']['status'])->toBe(['from' => 'ativa', 'to' => 'inativa'])
        ->and($log->metadata['changes']['role'])->toBe(['from' => 'Funcionario', 'to' => 'Moderador']);
});

it('returns not found when updating or resetting a user from another company', function () {
    $company = Company::factory()->create();
    signInCompanyAdministrator($company);
    $foreignUser = CompanyUser::factory()->for(Company::factory())->create();

    $this->patch(route('company.users.update', $foreignUser), [
        'name' => 'Invasor',
        'username' => 'invasor',
        'role' => 'Funcionario',
        'status' => 'inativa',
    ])->assertNotFound();
    $this->post(route('company.users.reset-password', $foreignUser))->assertNotFound();

    expect($foreignUser->fresh())
        ->status->toBe('ativa')
        ->must_change_password->toBeFalse();
    expect(AuditLog::query()->count())->toBe(0);
});

it('prevents an administrator from changing their own role or status', function () {
    $company = Company::factory()->create();
    $administrator = signInCompanyAdministrator($company);
    CompanyUser::factory()->for($company)->administrator()->create();

    $this->patch(route('company.users.update', $administrator), [
        'name' => $administrator->name,
        'username' => $administrator->username,
        'role' => 'Funcionario',
        'status' => 'ativa',
    ])->assertSessionHasErrors(['role' => 'Você não pode alterar o próprio perfil ou status. Peça a outro Administrador.']);

    expect($administrator->fresh()->role)->toBe('Administrador');
});

it('generates a temporary password for a user of the same company and audits it', function () {
    $company = Company::factory()->create();
    $administrator = signInCompanyAdministrator($company);
    $employee = CompanyUser::factory()->for($company)->create(['remember_token' => 'old-token']);

    $response = $this->post(route('company.users.reset-password', $employee));

    $response->assertRedirect();
    $temporaryPassword = session('temporaryPassword.password');
    expect($temporaryPassword)->toBeString()->toHaveLength(18);
    $employee->refresh();
    expect(Hash::check($temporaryPassword, $employee->password))->toBeTrue()
        ->and($employee->must_change_password)->toBeTrue()
        ->and($employee->remember_token)->not->toBe('old-token');

    $log = AuditLog::query()->where('action', 'company_user.password_reset')->sole();
    expect($log)
        ->company_id->toBe($company->id)
        ->actor_id->toBe($administrator->id)
        ->subject_id->toBe($employee->id)
        ->metadata->toBe(['source' => 'company_admin', 'username' => $employee->username]);
    expect(json_encode($log->metadata))->not->toContain($temporaryPassword);
});

it('does not let an administrator reset their own password through user management', function () {
    $company = Company::factory()->create();
    $administrator = signInCompanyAdministrator($company);

    $this->post(route('company.users.reset-password', $administrator))
        ->assertSessionHasErrors(['password' => 'Para trocar a sua própria senha, use a página Minha senha.']);

    expect($administrator->fresh()->must_change_password)->toBeFalse();
});

it('signs out existing sessions of a user whose password was reset and tells them who reset it', function () {
    $company = Company::factory()->create();
    $employee = CompanyUser::factory()->for($company)->create();
    $administrator = CompanyUser::factory()->for($company)->administrator()->create();

    $this->actingAs($company, 'company')
        ->post(route('company.user.login'), ['username' => $employee->username, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();

    $temporaryPassword = app(ResetCompanyUserPassword::class)
        ->handle($employee->fresh(), $administrator, 'company_admin')['password'];
    auth()->guard('company_user')->forgetUser();

    $this->get(route('dashboard'))->assertRedirect(route('company.portal'));
    $this->assertGuest('company_user');

    $this->post(route('company.user.login'), ['username' => $employee->username, 'password' => $temporaryPassword]);
    $this->get(route('company.user.security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mustChangePassword', true)
            ->where('passwordResetBy', 'company_admin'));
});
