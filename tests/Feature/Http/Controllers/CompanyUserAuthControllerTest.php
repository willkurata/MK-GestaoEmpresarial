<?php

use App\Models\Company;
use App\Models\CompanyUser;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the locked company workspace before an individual user signs in', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($company, 'company')->get(route('company.portal'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('company/Portal')
        ->where('company.name', $company->name)
        ->where('company.cnpj', $company->cnpj),
    );
});

it('opens the dashboard after a company user signs in', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();

    $response = $this->actingAs($company, 'company')
        ->from(route('company.portal'))
        ->post(route('company.user.login'), [
            'username' => $companyUser->username,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($companyUser, 'company_user');
});

it('rejects a user account that belongs to another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $otherCompanyUser = CompanyUser::factory()->for($otherCompany)->create();

    $response = $this->actingAs($company, 'company')
        ->from(route('company.portal'))
        ->post(route('company.user.login'), [
            'username' => $otherCompanyUser->username,
            'password' => 'password',
        ]);

    $response->assertSessionHasErrors(['username'])->assertRedirect(route('company.portal'));
    $this->assertGuest('company_user');
});
