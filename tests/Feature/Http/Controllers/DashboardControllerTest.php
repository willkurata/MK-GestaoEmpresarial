<?php

use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('renders period totals for the signed-in company only', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $register = $company->cashRegisters()->create(['name' => 'Caixa', 'status' => 'ativo']);
    $session = $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $companyUser->id,
        'opened_at' => '2026-10-10 12:00:00',
        'opening_amount' => '0.00',
        'status' => 'fechado',
    ]);
    $sale = $company->sales()->create([
        'company_user_id' => $companyUser->id,
        'cash_register_id' => $register->id,
        'cash_session_id' => $session->id,
        'occurred_at' => '2026-10-10 15:00:00',
        'status' => 'finalizada',
        'subtotal' => '60.00',
        'discount' => '0.00',
        'total' => '60.00',
    ]);
    $sale->payment()->create([
        'company_id' => $company->id,
        'method' => 'Dinheiro',
        'amount' => '60.00',
        'status' => 'confirmado',
        'paid_at' => '2026-10-10 15:00:00',
    ]);
    $pixSale = $company->sales()->create([
        'company_user_id' => $companyUser->id,
        'cash_register_id' => $register->id,
        'cash_session_id' => $session->id,
        'occurred_at' => '2026-10-10 16:00:00',
        'status' => 'finalizada',
        'subtotal' => '40.00',
        'discount' => '0.00',
        'total' => '40.00',
    ]);
    $pixSale->payment()->create([
        'company_id' => $company->id,
        'method' => 'pix',
        'amount' => '40.00',
        'status' => 'confirmado',
        'paid_at' => '2026-10-10 16:00:00',
    ]);
    $expense = $company->expenses()->create([
        'description' => 'Aluguel',
        'amount' => '25.00',
        'type' => 'fixa',
        'expense_date' => '2026-10-10',
    ]);
    $otherCompany = Company::factory()->create();
    $otherCompany->expenses()->create([
        'description' => 'Despesa alheia',
        'amount' => '900.00',
        'type' => 'variavel',
        'expense_date' => '2026-10-10',
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->get(route('dashboard', [
        'period' => 'custom',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-10',
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('filters.period', 'custom')
        ->where('summary.salesCount', 2)
        ->where('summary.salesTotal', '100.00')
        ->where('summary.cashTotal', '60.00')
        ->where('summary.pixTotal', '40.00')
        ->where('summary.expenseCount', 1)
        ->where('summary.expenseTotal', '25.00')
        ->where('summary.fixedExpenseTotal', '25.00')
        ->where('summary.variableExpenseTotal', '0.00')
        ->where('recentActivities.0.id', 'sale-'.$pixSale->id)
        ->where('recentActivities.2.id', 'expense-'.$expense->id));
});

it('rejects a custom date range whose end is before its start', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('dashboard'))->get(route('dashboard', [
        'period' => 'custom',
        'start_date' => '2026-10-11',
        'end_date' => '2026-10-10',
    ]));

    $response->assertRedirect(route('dashboard'))->assertSessionHasErrors(['end_date']);
});

it('gives an employee the trend, team and risk indicators of their own company', function () {
    Carbon::setTestNow('2026-10-20 15:00:00');
    $company = Company::factory()->create();
    $employee = CompanyUser::factory()->for($company)->create(['role' => 'Funcionario']);
    CompanyUser::factory()->for($company)->inactive()->create();
    CompanyUser::factory()->for(Company::factory())->create();
    $register = $company->cashRegisters()->create(['name' => 'Caixa', 'status' => 'ativo']);
    $session = $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $employee->id,
        'opened_at' => '2026-10-20 11:00:00',
        'opening_amount' => '0.00',
        'status' => 'aberto',
    ]);
    $company->sales()->create([
        'company_user_id' => $employee->id,
        'cash_register_id' => $register->id,
        'cash_session_id' => $session->id,
        'occurred_at' => '2026-10-20 12:00:00',
        'status' => 'finalizada',
        'subtotal' => '50.00',
        'discount' => '0.00',
        'total' => '50.00',
    ]);
    $company->expenses()->create([
        'description' => 'Energia',
        'amount' => '45.00',
        'type' => 'fixa',
        'expense_date' => '2026-10-20',
    ]);
    $this->actingAs($company, 'company')->actingAs($employee, 'company_user');

    $response = $this->get(route('dashboard', ['period' => 'today']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('companyUser.role', 'Funcionario')
        ->where('summary.netInflow', '0.00')
        ->where('summary.salesVariation', null)
        ->where('team.activeUsersCount', 1)
        ->where('team.openSessionCount', 1)
        ->where('risk.level', 'high')
        ->where('trend.granularity', 'day')
        ->has('trend.points', 7)
        ->where('trend.points.0.key', '2026-10-14')
        ->where('trend.points.6.key', '2026-10-20')
        ->where('trend.points.6.sales', '50.00')
        ->where('trend.points.6.expenses', '45.00'));

    Carbon::setTestNow();
});

it('does not plot future days of the current month', function () {
    Carbon::setTestNow('2026-10-20 15:00:00');
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $this->get(route('dashboard', ['period' => 'this_month']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('trend.points', 20)
            ->where('trend.points.19.key', '2026-10-20')
            ->where('risk.level', 'none'));

    Carbon::setTestNow();
});
