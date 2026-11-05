<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only the signed-in company products sales and cash movements', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $register = $company->cashRegisters()->create(['name' => 'Caixa principal', 'status' => 'ativo']);
    $session = $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $companyUser->id,
        'opened_at' => now(),
        'opening_amount' => '100.00',
        'status' => 'aberto',
    ]);
    $sale = $company->sales()->create([
        'company_user_id' => $companyUser->id,
        'cash_register_id' => $register->id,
        'cash_session_id' => $session->id,
        'occurred_at' => now(),
        'status' => 'finalizada',
        'subtotal' => '10.00',
        'discount' => '0.00',
        'total' => '10.00',
    ]);
    $company->cashMovements()->create([
        'cash_session_id' => $session->id,
        'company_user_id' => $companyUser->id,
        'type' => 'venda',
        'method' => 'Dinheiro',
        'amount' => '10.00',
        'description' => 'Venda local',
        'occurred_at' => now(),
    ]);

    $otherCompany = Company::factory()->create();
    $otherCompanyUser = CompanyUser::factory()->for($otherCompany)->create();
    Product::factory()->for($otherCompany)->create();
    $otherRegister = $otherCompany->cashRegisters()->create(['name' => 'Outro caixa', 'status' => 'ativo']);
    $otherSession = $otherRegister->sessions()->create([
        'company_id' => $otherCompany->id,
        'company_user_id' => $otherCompanyUser->id,
        'opened_at' => now(),
        'opening_amount' => '500.00',
        'status' => 'aberto',
    ]);
    $otherCompany->sales()->create([
        'company_user_id' => $otherCompanyUser->id,
        'cash_register_id' => $otherRegister->id,
        'cash_session_id' => $otherSession->id,
        'occurred_at' => now(),
        'status' => 'finalizada',
        'subtotal' => '700.00',
        'discount' => '0.00',
        'total' => '700.00',
    ]);
    $otherCompany->cashMovements()->create([
        'cash_session_id' => $otherSession->id,
        'company_user_id' => $otherCompanyUser->id,
        'type' => 'venda',
        'method' => 'Dinheiro',
        'amount' => '700.00',
        'description' => 'Venda de outra empresa',
        'occurred_at' => now(),
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->get(route('pos.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cashier/Index')
        ->has('products', 1)
        ->where('products.0.id', $product->id)
        ->has('recentSales', 1)
        ->where('recentSales.0.id', $sale->id)
        ->has('cashMovements', 1)
        ->where('cashMovements.0.description', 'Venda local'));
});

it('rejects a sale that contains a product owned by another company', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $register = $company->cashRegisters()->create(['name' => 'Caixa principal', 'status' => 'ativo']);
    $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $companyUser->id,
        'opened_at' => now(),
        'opening_amount' => '0.00',
        'status' => 'aberto',
    ]);
    $otherCompany = Company::factory()->create();
    $otherProduct = Product::factory()->for($otherCompany)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->post(route('pos.sales.store'), [
        'items' => [['product_id' => $otherProduct->id, 'quantity' => 1]],
        'discount_type' => 'none',
        'discount_value' => '0.00',
        'payment_method' => 'Pix',
    ]);

    $response->assertUnprocessable();
    $this->assertDatabaseCount('sales', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('cash_movements', 0);
});

it('returns not found when a company tries to refund another company sale', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->administrator()->create();
    $otherCompany = Company::factory()->create();
    $otherCompanyUser = CompanyUser::factory()->for($otherCompany)->create();
    $otherRegister = $otherCompany->cashRegisters()->create(['name' => 'Caixa de outra empresa', 'status' => 'ativo']);
    $otherSession = $otherRegister->sessions()->create([
        'company_id' => $otherCompany->id,
        'company_user_id' => $otherCompanyUser->id,
        'opened_at' => now(),
        'opening_amount' => '0.00',
        'status' => 'aberto',
    ]);
    $otherSale = $otherCompany->sales()->create([
        'company_user_id' => $otherCompanyUser->id,
        'cash_register_id' => $otherRegister->id,
        'cash_session_id' => $otherSession->id,
        'occurred_at' => now(),
        'status' => 'finalizada',
        'subtotal' => '35.00',
        'discount' => '0.00',
        'total' => '35.00',
    ]);
    $payment = $otherSale->payment()->create([
        'company_id' => $otherCompany->id,
        'method' => 'Pix',
        'amount' => '35.00',
        'status' => 'confirmado',
        'paid_at' => now(),
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->post(route('pos.sales.refund', $otherSale), ['reason' => 'Solicitação inválida']);

    $response->assertNotFound();
    $this->assertDatabaseHas('sales', ['id' => $otherSale->id, 'status' => 'finalizada']);
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'confirmado']);
    $this->assertDatabaseCount('refunds', 0);
    $this->assertDatabaseCount('cash_movements', 0);
});

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
