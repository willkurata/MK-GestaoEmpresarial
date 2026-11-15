<?php

use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\Sale;
use Inertia\Testing\AssertableInertia as Assert;

function signInPos(Company $company, CompanyUser $companyUser): void
{
    test()->actingAs($company, 'company')->actingAs($companyUser, 'company_user');
}

/** @param array<int, array{product_id: int, quantity: int}> $items */
function cashSalePayload(array $items, string $received, string $discountType = 'none', string $discountValue = '0.00'): array
{
    return [
        'items' => $items,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'payment_method' => 'Dinheiro',
        'received_amount' => $received,
    ];
}

it('opens a turn on the permanent register and blocks a second open turn in the same company', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    $otherOperator = CompanyUser::factory()->for($company)->create();

    signInPos($company, $operator);
    $this->post(route('pos.open'), ['initial_cash' => '100.00'])->assertRedirect();

    $this->assertDatabaseCount('cash_registers', 1);
    $this->assertDatabaseHas('cash_sessions', [
        'company_id' => $company->id,
        'company_user_id' => $operator->id,
        'status' => 'aberto',
        'opening_amount' => '100.00',
    ]);

    signInPos($company, $otherOperator);
    $this->post(route('pos.open'), ['initial_cash' => '50.00'])->assertStatus(422);
    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page
        ->where('currentSession', null)
        ->where('cashHeldBy', $operator->name));

    $this->assertDatabaseCount('cash_registers', 1);
    $this->assertDatabaseCount('cash_sessions', 1);
});

it('rejects a sale when the operator has no open turn', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create(['sale_price' => '10.00', 'status' => 'ativo']);
    signInPos($company, $operator);

    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $product->id, 'quantity' => 1]], '10.00'))
        ->assertStatus(422);

    $this->assertDatabaseCount('sales', 0);
    $this->assertDatabaseCount('cash_movements', 0);
});

it('records a cash sale with percentage discount, change and price snapshots', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create([
        'name' => 'Bolo de fubá',
        'sale_price' => '12.50',
        'cost' => '4.00',
        'status' => 'ativo',
    ]);
    signInPos($company, $operator);
    $this->post(route('pos.open'), ['initial_cash' => '0.00']);

    $this->post(route('pos.sales.store'), cashSalePayload(
        [['product_id' => $product->id, 'quantity' => 3]],
        '50.00',
        'percentage',
        '10.00',
    ))->assertRedirect()->assertSessionHasNoErrors();

    $sale = Sale::query()->sole();
    expect((string) $sale->subtotal)->toBe('37.50')
        ->and((string) $sale->discount)->toBe('3.75')
        ->and((string) $sale->total)->toBe('33.75')
        ->and($sale->status)->toBe('finalizada');
    $this->assertDatabaseHas('payments', [
        'sale_id' => $sale->id,
        'method' => 'Dinheiro',
        'amount' => '33.75',
        'amount_received' => '50.00',
        'change_amount' => '16.25',
    ]);
    $this->assertDatabaseHas('cash_movements', [
        'cash_session_id' => $sale->cash_session_id,
        'type' => 'venda',
        'method' => 'Dinheiro',
        'amount' => '33.75',
    ]);

    $product->update(['name' => 'Bolo novo', 'sale_price' => '20.00', 'cost' => '9.00']);
    $this->assertDatabaseHas('sale_items', [
        'sale_id' => $sale->id,
        'product_name' => 'Bolo de fubá',
        'quantity' => 3,
        'unit_price' => '12.50',
        'unit_cost' => '4.00',
    ]);
});

it('rejects inactive products, a fixed discount above the subtotal stays at zero total, and insufficient cash', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    $inactive = Product::factory()->for($company)->create(['sale_price' => '5.00', 'status' => 'inativo']);
    $active = Product::factory()->for($company)->create(['sale_price' => '8.00', 'status' => 'ativo']);
    signInPos($company, $operator);
    $this->post(route('pos.open'), ['initial_cash' => '0.00']);

    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $inactive->id, 'quantity' => 1]], '5.00'))
        ->assertStatus(422);
    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $active->id, 'quantity' => 1]], '7.99'))
        ->assertStatus(422);
    $this->assertDatabaseCount('sales', 0);

    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $active->id, 'quantity' => 1]], '0.00', 'fixed', '50.00'))
        ->assertRedirect();
    $sale = Sale::query()->sole();
    expect((string) $sale->discount)->toBe('8.00')->and((string) $sale->total)->toBe('0.00');
});

it('forbids an employee from refunding a sale', function () {
    [$company, $seller, $sale] = posSaleFixture('Pix', '30.00');
    $employee = CompanyUser::factory()->for($company)->create();
    signInPos($company, $employee);

    $this->post(route('pos.sales.refund', $sale), ['reason' => 'Cliente desistiu'])->assertForbidden();

    $this->assertDatabaseCount('refunds', 0);
    expect($sale->fresh()->status)->toBe('finalizada');
});

it('refunds a Pix sale once, in the same method, and blocks a duplicate refund', function () {
    [$company, $seller, $sale] = posSaleFixture('Pix', '30.00');
    $moderator = CompanyUser::factory()->for($company)->moderator()->create();
    signInPos($company, $seller);
    $this->post(route('pos.close'), ['counted_cash' => '0.00']);

    signInPos($company, $moderator);
    $this->post(route('pos.sales.refund', $sale), ['reason' => 'Sem caixa aberto'])->assertStatus(422);
    $this->post(route('pos.open'), ['initial_cash' => '0.00']);

    $this->post(route('pos.sales.refund', $sale), ['reason' => '   '])->assertSessionHasErrors('reason');
    $this->post(route('pos.sales.refund', $sale), ['reason' => 'Pix devolvido ao cliente'])->assertRedirect();
    $this->post(route('pos.sales.refund', $sale), ['reason' => 'Duplicado'])->assertStatus(422);

    $this->assertDatabaseCount('refunds', 1);
    $this->assertDatabaseHas('refunds', [
        'company_id' => $company->id,
        'company_user_id' => $moderator->id,
        'amount' => '30.00',
        'method' => 'Pix',
        'reason' => 'Pix devolvido ao cliente',
    ]);
    $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'estornada', 'total' => '30.00']);
    $this->assertDatabaseHas('payments', ['sale_id' => $sale->id, 'status' => 'estornado', 'amount' => '30.00']);
    $moderatorSession = CashSession::query()->where('company_user_id', $moderator->id)->sole();
    $this->assertDatabaseHas('cash_movements', [
        'cash_session_id' => $moderatorSession->id,
        'type' => 'estorno',
        'method' => 'Pix',
        'amount' => '-30.00',
    ]);
});

it('closes a turn with expected cash = opening + cash sales - withdrawals - cash refunds, ignoring Pix, and keeps the difference', function () {
    $company = Company::factory()->create();
    $admin = CompanyUser::factory()->for($company)->administrator()->create();
    $product = Product::factory()->for($company)->create(['sale_price' => '10.00', 'status' => 'ativo']);
    signInPos($company, $admin);
    $this->post(route('pos.open'), ['initial_cash' => '100.00']);

    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $product->id, 'quantity' => 3]], '50.00'));
    $this->post(route('pos.sales.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
        'discount_type' => 'none',
        'discount_value' => '0.00',
        'payment_method' => 'Pix',
    ]);
    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $product->id, 'quantity' => 1]], '10.00'));
    $cashSale = Sale::query()->latest('id')->first();
    $this->post(route('pos.sales.refund', $cashSale), ['reason' => 'Produto errado'])->assertRedirect();
    $this->post(route('pos.withdraw'), ['amount' => '20.00', 'description' => 'Sangria'])->assertRedirect();

    $this->post(route('pos.close'), ['counted_cash' => '105.50'])->assertRedirect();

    $this->assertDatabaseHas('cash_sessions', [
        'company_id' => $company->id,
        'status' => 'fechado',
        'expected_amount' => '110.00',
        'counted_amount' => '105.50',
        'difference_amount' => '-4.50',
    ]);

    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $product->id, 'quantity' => 1]], '10.00'))
        ->assertStatus(422);
    $this->post(route('pos.withdraw'), ['amount' => '1.00', 'description' => 'Após fechar'])->assertNotFound();
});

it('computes the expected closing cash exactly for amounts with cents', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    $cheap = Product::factory()->for($company)->create(['sale_price' => '0.10', 'status' => 'ativo']);
    $other = Product::factory()->for($company)->create(['sale_price' => '0.20', 'status' => 'ativo']);
    signInPos($company, $operator);
    $this->post(route('pos.open'), ['initial_cash' => '0.00']);

    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $cheap->id, 'quantity' => 1]], '0.10'));
    $this->post(route('pos.sales.store'), cashSalePayload([['product_id' => $other->id, 'quantity' => 1]], '0.20'));

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->where('cashAvailable', '0.30'));
    $this->post(route('pos.withdraw'), ['amount' => '0.30', 'description' => 'Retirada total'])->assertSessionHasNoErrors();
    $this->post(route('pos.close'), ['counted_cash' => '0.00'])->assertRedirect();

    $this->assertDatabaseHas('cash_sessions', [
        'status' => 'fechado',
        'expected_amount' => '0.00',
        'difference_amount' => '0.00',
    ]);
});

it('keeps the turn open after the operator logs out and blocks other operators until it is closed', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    $nextOperator = CompanyUser::factory()->for($company)->create();
    signInPos($company, $operator);
    $this->post(route('pos.open'), ['initial_cash' => '10.00']);
    $this->post(route('company.user.logout'))->assertRedirect(route('company.portal'));

    signInPos($company, $nextOperator);
    $this->post(route('pos.close'), ['counted_cash' => '10.00'])->assertNotFound();
    $this->assertDatabaseHas('cash_sessions', ['company_user_id' => $operator->id, 'status' => 'aberto']);
});

it('signs out an operator that was deactivated while signed in', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    signInPos($company, $operator);
    $operator->update(['status' => 'inativa']);

    $this->get(route('pos.index'))->assertRedirect(route('company.portal'));
    $this->assertGuest('company_user');
});

it('signs everyone out when the company is deactivated', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    signInPos($company, $operator);
    $company->update(['status' => 'inativa']);

    $this->get(route('pos.index'))->assertRedirect(route('home'));
    $this->assertGuest('company');
    $this->assertGuest('company_user');
});

it('does not expose password hashes in shared Inertia props', function () {
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->create();
    signInPos($company, $operator);

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page
        ->missing('auth.company.password')
        ->missing('auth.company.remember_token')
        ->missing('auth.companyUser.password')
        ->missing('auth.companyUser.remember_token'));
});

/** @return array{0: Company, 1: CompanyUser, 2: Sale} */
function posSaleFixture(string $method, string $price): array
{
    $company = Company::factory()->create();
    $seller = CompanyUser::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create(['sale_price' => $price, 'status' => 'ativo']);
    signInPos($company, $seller);
    test()->post(route('pos.open'), ['initial_cash' => '0.00']);
    test()->post(route('pos.sales.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'discount_type' => 'none',
        'discount_value' => '0.00',
        'payment_method' => $method,
        'received_amount' => $method === 'Dinheiro' ? $price : null,
    ])->assertRedirect();

    return [$company, $seller, Sale::query()->sole()];
}
