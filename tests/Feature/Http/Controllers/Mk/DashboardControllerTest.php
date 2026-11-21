<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 15:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function recordMkDashboardSale(Company $company, CompanyUser $operator, string $total, string $occurredAt): void
{
    $register = $company->cashRegisters()->firstOrCreate([], ['name' => 'Caixa principal', 'status' => 'ativo']);
    $session = $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $operator->id,
        'opened_at' => $occurredAt,
        'opening_amount' => '0.00',
        'status' => 'fechado',
    ]);
    $company->sales()->create([
        'company_user_id' => $operator->id,
        'cash_register_id' => $register->id,
        'cash_session_id' => $session->id,
        'occurred_at' => $occurredAt,
        'status' => 'finalizada',
        'subtotal' => $total,
        'discount' => '0.00',
        'total' => $total,
    ]);
}

it('shows one performance card per company with isolated month-to-date indicators', function () {
    $superAdmin = User::factory()->create();
    $healthy = Company::factory()->create(['name' => 'A Cafeteria']);
    $operator = CompanyUser::factory()->for($healthy)->create();
    CompanyUser::factory()->for($healthy)->inactive()->create();
    recordMkDashboardSale($healthy, $operator, '300.00', '2026-10-10 15:00:00');
    recordMkDashboardSale($healthy, $operator, '200.00', '2026-09-10 15:00:00');
    recordMkDashboardSale($healthy, $operator, '999.00', '2026-09-25 15:00:00');
    $healthy->expenses()->create(['description' => 'Aluguel', 'amount' => '60.00', 'type' => 'fixa', 'expense_date' => '2026-10-05']);

    $struggling = Company::factory()->create(['name' => 'B Padaria']);
    CompanyUser::factory()->for($struggling)->create();
    $struggling->expenses()->create(['description' => 'Energia', 'amount' => '80.00', 'type' => 'fixa', 'expense_date' => '2026-10-05']);

    Company::factory()->inactive()->create(['name' => 'C Inativa']);

    $this->actingAs($superAdmin)
        ->get(route('mk.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mk/Dashboard')
            ->where('period.startDate', '2026-10-01')
            ->where('period.endDate', '2026-10-20')
            ->where('period.previousEndDate', '2026-09-20')
            ->has('companies', 3)
            ->where('companies.0.name', 'A Cafeteria')
            ->where('companies.0.metrics.revenue', '300.00')
            ->where('companies.0.metrics.previousRevenue', '200.00')
            ->where('companies.0.metrics.revenueVariation', 50)
            ->where('companies.0.metrics.salesCount', 1)
            ->where('companies.0.metrics.expenses', '60.00')
            ->where('companies.0.metrics.activeUsersCount', 1)
            ->where('companies.0.metrics.usersCount', 2)
            ->where('companies.0.risk.level', 'ok')
            ->where('companies.1.metrics.revenue', '0.00')
            ->where('companies.1.metrics.expenses', '80.00')
            ->where('companies.1.risk.level', 'critical')
            ->where('companies.2.risk.level', 'none')
            ->where('totals.revenue', '300.00')
            ->where('totals.companiesCount', 3)
            ->where('totals.activeCompaniesCount', 2)
            ->where('totals.riskCounts.critical', 1)
            ->where('totals.riskCounts.ok', 1)
            ->has('trend', 6)
            ->where('trend.5.month', '2026-10')
            ->where('trend.5.revenue', '300.00')
            ->where('trend.5.expenses', '140.00'));
});

it('keeps the MK dashboard restricted to MK administrators', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();

    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');
    // actingAs() troca o guard padrão; a aplicação real usa sempre "web" nas rotas MK.
    auth()->shouldUse('web');

    $this->get(route('mk.dashboard'))
        ->assertRedirect(route('login'));
});
