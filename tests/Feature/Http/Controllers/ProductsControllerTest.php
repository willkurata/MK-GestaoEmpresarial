<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;

it('stores an optional product barcode for the current company', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('products.index'))->post(route('products.store'), [
        'name' => 'Café coado',
        'barcode' => '7891000000001',
        'sale_price' => '8.50',
        'cost' => null,
        'active' => true,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', [
        'company_id' => $company->id,
        'name' => 'Café coado',
        'barcode' => '7891000000001',
    ]);
});

it('rejects a barcode already used by another product in the same company', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    Product::factory()->for($company)->create(['barcode' => '7891000000001']);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('products.index'))->post(route('products.store'), [
        'name' => 'Café expresso',
        'barcode' => '7891000000001',
        'sale_price' => '9.00',
        'cost' => null,
        'active' => true,
    ]);

    $response->assertSessionHasErrors(['barcode'])->assertRedirect(route('products.index'));
    $this->assertDatabaseCount('products', 1);
});

it('allows different companies to use the same product barcode', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $otherCompany = Company::factory()->create();
    Product::factory()->for($otherCompany)->create(['barcode' => '7891000000001']);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('products.index'))->post(route('products.store'), [
        'name' => 'Café coado',
        'barcode' => '7891000000001',
        'sale_price' => '8.50',
        'cost' => null,
        'active' => true,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
    $this->assertDatabaseCount('products', 2);
    $this->assertDatabaseHas('products', [
        'company_id' => $company->id,
        'barcode' => '7891000000001',
    ]);
});

it('keeps same-name products and their prices separate between companies', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $otherCompany = Company::factory()->create();
    Product::factory()->for($otherCompany)->create([
        'name' => 'Café',
        'sale_price' => '5.00',
    ]);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('products.index'))->post(route('products.store'), [
        'name' => 'Café',
        'sale_price' => '1.00',
        'cost' => null,
        'active' => true,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', [
        'company_id' => $company->id,
        'name' => 'Café',
        'sale_price' => '1.00',
    ]);
    $this->assertDatabaseHas('products', [
        'company_id' => $otherCompany->id,
        'name' => 'Café',
        'sale_price' => '5.00',
    ]);
});

it('updates a product while keeping its own barcode', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create(['barcode' => '7891000000001']);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('products.index'))->patch(route('products.update', $product), [
        'name' => 'Café coado especial',
        'barcode' => '7891000000001',
        'sale_price' => '9.50',
        'cost' => null,
        'active' => true,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Café coado especial',
        'barcode' => '7891000000001',
    ]);
});

it('returns not found when another company tries to update a product barcode', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $otherCompany = Company::factory()->create();
    $product = Product::factory()->for($otherCompany)->create(['barcode' => '7891000000001']);
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->patch(route('products.update', $product), [
        'name' => 'Produto alterado',
        'barcode' => '7891000000002',
        'sale_price' => '10.00',
        'cost' => null,
        'active' => true,
    ]);

    $response->assertNotFound();
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'company_id' => $otherCompany->id,
        'name' => $product->name,
        'barcode' => '7891000000001',
    ]);
});
