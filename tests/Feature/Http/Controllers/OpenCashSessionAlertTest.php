<?php

use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-11-24 15:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function openCashSessionFor(Company $company, CompanyUser $operator, string $openedAt): CashSession
{
    $register = $company->cashRegisters()->firstOrCreate([], ['name' => 'Caixa principal', 'status' => 'ativo']);

    return $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $operator->id,
        'opened_at' => $openedAt,
        'opening_amount' => '50.00',
        'status' => 'aberto',
    ]);
}

it('warns on the company sign-in screen who left the cash register open', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create(['name' => 'Ana Operadora']);
    openCashSessionFor($company, $operator, '2026-11-24 12:00:00');

    $otherCompany = Company::factory()->create();
    openCashSessionFor($otherCompany, CompanyUser::factory()->for($otherCompany)->create(), '2026-11-24 12:00:00');

    $this->actingAs($company, 'company')
        ->get(route('company.portal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/Portal')
            ->has('openCashSessions', 1)
            ->where('openCashSessions.0.operator', 'Ana Operadora')
            ->where('openCashSessions.0.register', 'Caixa principal')
            ->where('openCashSessions.0.company.name', $company->name)
            ->where('openCashSessions.0.openedAt', '24/11/2026 09:00')
            ->where('openCashSessions.0.hoursOpen', 3)
            ->where('openCashSessions.0.isStale', false),
        );
});

it('shows no warning once every shift is closed', function () {
    $company = Company::factory()->create();
    $session = openCashSessionFor($company, CompanyUser::factory()->for($company)->create(), '2026-11-24 12:00:00');
    $session->update(['status' => 'fechado', 'closed_at' => now()]);

    $this->actingAs($company, 'company')
        ->get(route('company.portal'))
        ->assertInertia(fn (Assert $page) => $page->has('openCashSessions', 0));
});

it('flags a shift opened on a previous business day as stale on the company dashboard', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create(['name' => 'Bruno']);
    // 23/11 23:30 in São Paulo is already 24/11 in UTC: still the previous business day.
    openCashSessionFor($company, $operator, '2026-11-24 02:30:00');

    $this->actingAs($company, 'company')
        ->actingAs($operator, 'company_user')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('openCashSessions.0.operator', 'Bruno')
            ->where('openCashSessions.0.openedAt', '23/11/2026 23:30')
            ->where('openCashSessions.0.isStale', true),
        );
});

it('lists open shifts of every company on the MK admin dashboard', function () {
    $user = User::factory()->create();
    $bakery = Company::factory()->create(['name' => 'Padaria Aurora']);
    $cafe = Company::factory()->create(['name' => 'Café Brisa']);
    openCashSessionFor($bakery, CompanyUser::factory()->for($bakery)->create(['name' => 'Carla']), '2026-11-22 11:00:00');
    openCashSessionFor($cafe, CompanyUser::factory()->for($cafe)->create(['name' => 'Davi']), '2026-11-24 13:00:00');

    $this->actingAs($user)
        ->get(route('mk.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mk/Dashboard')
            ->has('openCashSessions', 2)
            ->where('openCashSessions.0.company.name', 'Padaria Aurora')
            ->where('openCashSessions.0.operator', 'Carla')
            ->where('openCashSessions.0.isStale', true)
            ->where('openCashSessions.1.company.name', 'Café Brisa')
            ->where('openCashSessions.1.operator', 'Davi')
            ->where('openCashSessions.1.isStale', false),
        );
});

it('shows only that company open shifts on the MK company page', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    openCashSessionFor($company, CompanyUser::factory()->for($company)->create(['name' => 'Elisa']), '2026-11-24 13:00:00');
    $otherCompany = Company::factory()->create();
    openCashSessionFor($otherCompany, CompanyUser::factory()->for($otherCompany)->create(), '2026-11-24 13:00:00');

    $this->actingAs($user)
        ->get(route('mk.companies.show', $company))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('openCashSessions', 1)
            ->where('openCashSessions.0.operator', 'Elisa'),
        );
});

it('refuses to open a new shift while another operator keeps the cash register open', function () {
    $company = Company::factory()->create();
    $holder = CompanyUser::factory()->for($company)->create(['name' => 'Fábio']);
    $newcomer = CompanyUser::factory()->for($company)->create();
    openCashSessionFor($company, $holder, '2026-11-24 12:00:00');

    $this->actingAs($company, 'company')
        ->actingAs($newcomer, 'company_user')
        ->from(route('pos.index'))
        ->post(route('pos.open'), ['opening_amount' => '20.00'])
        ->assertRedirect(route('pos.index'))
        ->assertSessionHasErrors([
            'opening_amount' => 'O caixa está aberto por Fábio desde 24/11/2026 às 09:00. Esse operador precisa fechar o turno antes de um novo ser aberto.',
        ]);

    expect($company->cashSessions()->where('status', 'aberto')->count())->toBe(1);
});

it('tells the operator to close their own open shift before opening another', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    openCashSessionFor($company, $operator, '2026-11-24 12:00:00');

    $this->actingAs($company, 'company')
        ->actingAs($operator, 'company_user')
        ->from(route('pos.index'))
        ->post(route('pos.open'), ['opening_amount' => '20.00'])
        ->assertSessionHasErrors([
            'opening_amount' => 'Você já tem um caixa aberto desde 24/11/2026 às 09:00. Feche esse turno antes de abrir outro.',
        ]);

    expect($company->cashSessions()->count())->toBe(1);
});
