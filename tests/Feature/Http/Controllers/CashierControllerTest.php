<?php

use App\Models\Company;
use App\Models\CompanyUser;
use Inertia\Testing\AssertableInertia as Assert;

it('records a cash withdrawal and subtracts it from the expected closing balance', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $register = $company->cashRegisters()->create(['name' => 'Caixa principal', 'status' => 'ativo']);
    $session = $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $companyUser->id,
        'opened_at' => now(),
        'opening_amount' => '100.00',
        'status' => 'aberto',
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $withdrawalResponse = $this->from(route('pos.index'))->post(route('pos.withdraw'), [
        'amount' => '25.00',
        'description' => 'Pagamento de entrega',
    ]);

    $withdrawalResponse->assertSessionHasNoErrors()->assertRedirect(route('pos.index'));
    $this->assertDatabaseHas('cash_movements', [
        'company_id' => $company->id,
        'cash_session_id' => $session->id,
        'company_user_id' => $companyUser->id,
        'type' => 'retirada',
        'method' => 'Dinheiro',
        'amount' => '-25.00',
        'description' => 'Pagamento de entrega',
    ]);
    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page
        ->where('cashAvailable', '75.00')
        ->has('cashMovements', 1)
        ->where('cashMovements.0.type', 'retirada')
        ->where('cashMovements.0.amount', '-25.00'));

    $this->post(route('pos.close'), ['counted_cash' => '75.00'])->assertRedirect(route('pos.index'));

    $this->assertDatabaseHas('cash_sessions', [
        'id' => $session->id,
        'status' => 'fechado',
        'expected_amount' => '75.00',
        'counted_amount' => '75.00',
        'difference_amount' => '0.00',
    ]);
});

it('rejects a withdrawal greater than the remaining cash and preserves existing movements', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $register = $company->cashRegisters()->create(['name' => 'Caixa principal', 'status' => 'ativo']);
    $session = $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $companyUser->id,
        'opened_at' => now(),
        'opening_amount' => '10.00',
        'status' => 'aberto',
    ]);
    $company->cashMovements()->create([
        'cash_session_id' => $session->id,
        'company_user_id' => $companyUser->id,
        'type' => 'venda',
        'method' => 'Dinheiro',
        'amount' => '5.00',
        'description' => 'Venda anterior',
        'occurred_at' => now(),
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('pos.index'))->post(route('pos.withdraw'), [
        'amount' => '15.01',
        'description' => 'Retirada acima do saldo',
    ]);

    $response->assertRedirect(route('pos.index'))->assertSessionHasErrors(['amount']);
    $this->assertDatabaseCount('cash_movements', 1);
});

it('does not let an operator withdraw from another operator’s open turn', function () {
    $company = Company::factory()->create();
    $signedInUser = CompanyUser::factory()->for($company)->create();
    $cashier = CompanyUser::factory()->for($company)->create();
    $register = $company->cashRegisters()->create(['name' => 'Caixa principal', 'status' => 'ativo']);
    $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $cashier->id,
        'opened_at' => now(),
        'opening_amount' => '100.00',
        'status' => 'aberto',
    ]);
    $this->actingAs($company, 'company')->actingAs($signedInUser, 'company_user');

    $response = $this->post(route('pos.withdraw'), [
        'amount' => '10.00',
        'description' => 'Retirada indevida',
    ]);

    $response->assertNotFound();
    $this->assertDatabaseCount('cash_movements', 0);
});
