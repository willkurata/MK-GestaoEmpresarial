<?php

use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

it('applies the V1 permission matrix to each role', function (string $role, array $allowed) {
    $companyUser = CompanyUser::factory()->make(['role' => $role, 'status' => 'ativa']);

    foreach (['reverse-sales', 'manage-products', 'manage-expenses', 'view-reports', 'manage-users', 'withdraw-from-any-shift'] as $ability) {
        expect(Gate::forUser($companyUser)->allows($ability))
            ->toBe(in_array($ability, $allowed, true), "{$role} / {$ability}");
    }
})->with([
    'Administrador' => ['Administrador', ['reverse-sales', 'manage-products', 'manage-expenses', 'view-reports', 'manage-users', 'withdraw-from-any-shift']],
    'Moderador' => ['Moderador', ['reverse-sales', 'manage-products', 'manage-expenses', 'view-reports', 'withdraw-from-any-shift']],
    'Funcionario' => ['Funcionario', []],
]);

it('grants nothing to an inactive administrator', function () {
    $companyUser = CompanyUser::factory()->administrator()->inactive()->make();

    expect(Gate::forUser($companyUser)->any(array_keys(CompanyUser::PERMISSIONS)))->toBeFalse();
});

it('refuses employee access to restricted modules', function (string $method, string $routeName, array $parameters) {
    $company = Company::factory()->create();
    $employee = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($employee, 'company_user');

    $this->{$method}(route($routeName, $parameters))->assertForbidden();
})->with([
    'products store' => ['post', 'products.store', []],
    'products update' => ['patch', 'products.update', ['product' => 1]],
    'expenses index' => ['get', 'expenses.index', []],
    'expenses store' => ['post', 'expenses.store', []],
    'expenses update' => ['patch', 'expenses.update', ['expense' => 1]],
    'reports' => ['get', 'reports.index', []],
    'refund' => ['post', 'pos.sales.refund', ['sale' => 1]],
]);

it('keeps the dashboard, product list and cashier available to employees', function (string $routeName) {
    $company = Company::factory()->create();
    $employee = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($employee, 'company_user');

    $this->get(route($routeName))->assertOk();
})->with(['dashboard', 'products.index', 'pos.index']);

it('shares the signed-in user permissions with the frontend', function () {
    $company = Company::factory()->create();
    $moderator = CompanyUser::factory()->for($company)->moderator()->create();
    $this->actingAs($company, 'company')->actingAs($moderator, 'company_user');

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.permissions', [
            'reverseSales' => true,
            'manageProducts' => true,
            'manageExpenses' => true,
            'viewReports' => true,
            'manageUsers' => false,
        ]));
});
