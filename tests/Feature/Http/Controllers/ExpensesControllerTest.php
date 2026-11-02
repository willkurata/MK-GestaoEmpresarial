<?php

use App\Models\Company;
use App\Models\CompanyUser;

it('updates an expense belonging to the signed-in company', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $expense = $company->expenses()->create([
        'description' => 'Conta de energia',
        'amount' => '125.00',
        'type' => 'fixa',
        'expense_date' => '2026-10-01',
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('expenses.index'))->patch(route('expenses.update', $expense), [
        'description' => 'Conta de energia revisada',
        'amount' => '140.50',
        'type' => 'variavel',
        'expense_date' => '2026-10-05',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('expenses.index'));
    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'company_id' => $company->id,
        'description' => 'Conta de energia revisada',
        'amount' => '140.50',
        'type' => 'variavel',
        'expense_date' => '2026-10-05',
    ]);
});

it('returns not found when another company tries to update an expense', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $otherCompany = Company::factory()->create();
    $expense = $otherCompany->expenses()->create([
        'description' => 'Despesa de outra empresa',
        'amount' => '75.00',
        'type' => 'variavel',
        'expense_date' => '2026-10-01',
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->patch(route('expenses.update', $expense), [
        'description' => 'Tentativa de alteração',
        'amount' => '80.00',
        'type' => 'fixa',
        'expense_date' => '2026-10-06',
    ]);

    $response->assertNotFound();
    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'company_id' => $otherCompany->id,
        'description' => 'Despesa de outra empresa',
        'amount' => '75.00',
    ]);
});

it('rejects a non-positive amount when updating an expense', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $expense = $company->expenses()->create([
        'description' => 'Material de limpeza',
        'amount' => '45.00',
        'type' => 'variavel',
        'expense_date' => '2026-10-01',
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('expenses.index'))->patch(route('expenses.update', $expense), [
        'description' => 'Material de limpeza',
        'amount' => '0',
        'type' => 'variavel',
        'expense_date' => '2026-10-01',
    ]);

    $response->assertSessionHasErrors(['amount'])->assertRedirect(route('expenses.index'));
    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'amount' => '45.00',
        'description' => 'Material de limpeza',
    ]);
});
