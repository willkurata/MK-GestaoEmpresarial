<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Company, 1: CompanyUser}
 */
function companyWithOpenCashSession(): array
{
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

    return [$company, $companyUser];
}

it('decrements tracked stock when a sale is finalized and leaves untracked products unlimited', function () {
    [$company, $companyUser] = companyWithOpenCashSession();
    $tracked = Product::factory()->for($company)->create(['sale_price' => '5.00', 'stock_quantity' => 10]);
    $untracked = Product::factory()->for($company)->create(['sale_price' => '8.00', 'stock_quantity' => null]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->post(route('pos.sales.store'), [
        'items' => [
            ['product_id' => $tracked->id, 'quantity' => 3],
            ['product_id' => $untracked->id, 'quantity' => 50],
        ],
        'discount_type' => 'none',
        'discount_value' => '0.00',
        'payment_method' => 'Pix',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    expect($tracked->fresh()->stock_quantity)->toBe(7)
        ->and($untracked->fresh()->stock_quantity)->toBeNull();
});

it('rejects a sale above the available stock without recording anything', function () {
    [$company, $companyUser] = companyWithOpenCashSession();
    $product = Product::factory()->for($company)->create(['name' => 'Bolo de cenoura', 'stock_quantity' => 2]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->post(route('pos.sales.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 3]],
        'discount_type' => 'none',
        'discount_value' => '0.00',
        'payment_method' => 'Pix',
    ]);

    $response->assertSessionHasErrors(['items' => 'Estoque insuficiente: Bolo de cenoura (disponível: 2).']);
    expect($product->fresh()->stock_quantity)->toBe(2);
    $this->assertDatabaseCount('sales', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('cash_movements', 0);
});

it('stores and updates stock fields from the product form', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $this->from(route('products.index'))->post(route('products.store'), [
        'name' => 'Suco de laranja',
        'sale_price' => '9.00',
        'stock_quantity' => 12,
        'low_stock_threshold' => 3,
        'active' => true,
    ])->assertSessionHasNoErrors();
    $product = $company->products()->sole();
    expect($product->stock_quantity)->toBe(12)->and($product->low_stock_threshold)->toBe(3);

    $this->from(route('products.index'))->patch(route('products.update', $product), [
        'name' => 'Suco de laranja',
        'sale_price' => '9.00',
        'stock_quantity' => null,
        'low_stock_threshold' => 3,
        'status' => 'ativo',
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->stock_quantity)->toBeNull()
        ->and($product->fresh()->low_stock_threshold)->toBeNull();
});

it('rejects negative or fractional stock quantities', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $this->from(route('products.index'))->post(route('products.store'), [
        'name' => 'Pão de queijo',
        'sale_price' => '6.00',
        'stock_quantity' => -1,
        'low_stock_threshold' => 1.5,
    ])->assertSessionHasErrors(['stock_quantity', 'low_stock_threshold']);

    $this->assertDatabaseCount('products', 0);
});

it('lists only the signed-in company stock and flags low or empty stock', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $empty = Product::factory()->for($company)->create(['name' => 'A vazio', 'stock_quantity' => 0, 'low_stock_threshold' => null]);
    $low = Product::factory()->for($company)->create(['name' => 'B baixo', 'stock_quantity' => 3, 'low_stock_threshold' => 5]);
    $normal = Product::factory()->for($company)->create(['name' => 'C normal', 'stock_quantity' => 20, 'low_stock_threshold' => 5]);
    Product::factory()->for($company)->create(['name' => 'D sem controle', 'stock_quantity' => null]);
    Product::factory()->for(Company::factory())->create(['stock_quantity' => 0]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->get(route('stock.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Stock/Index')
        ->has('products', 4)
        ->where('products.0.id', $empty->id)
        ->where('products.0.low_stock', true)
        ->where('products.1.id', $low->id)
        ->where('products.1.low_stock', true)
        ->where('products.2.id', $normal->id)
        ->where('products.2.low_stock', false)
        ->where('summary', ['tracked' => 3, 'untracked' => 1, 'lowStock' => 2])
    );
});
