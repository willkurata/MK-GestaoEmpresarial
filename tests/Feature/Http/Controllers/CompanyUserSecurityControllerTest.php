<?php

use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('renders password settings for an authenticated company user', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();

    $response = $this->actingAs($company, 'company')
        ->actingAs($companyUser, 'company_user')
        ->get(route('company.user.security.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('company/Security'));
});

it('changes the authenticated company user password without changing another company user', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $otherCompany = Company::factory()->create();
    $otherCompanyUser = CompanyUser::factory()->for($otherCompany)->create();

    $response = $this->actingAs($company, 'company')
        ->actingAs($companyUser, 'company_user')
        ->put(route('company.user.password.update'), [
            'current_password' => 'password',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

    $response->assertRedirect();
    expect(Hash::check('NewSecurePassword123!', $companyUser->fresh()->password))->toBeTrue();
    expect(Hash::check('password', $otherCompanyUser->fresh()->password))->toBeTrue();
    $this->assertAuthenticatedAs($companyUser, 'company_user');
});

it('rejects a password change when the current password is incorrect', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $originalPasswordHash = $companyUser->password;

    $response = $this->actingAs($company, 'company')
        ->actingAs($companyUser, 'company_user')
        ->from(route('company.user.security.edit'))
        ->put(route('company.user.password.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

    $response->assertRedirect(route('company.user.security.edit'))->assertSessionHasErrors(['current_password']);
    expect($companyUser->fresh()->password)->toBe($originalPasswordHash);
});

it('rejects a password change when the new password confirmation does not match', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $originalPasswordHash = $companyUser->password;

    $response = $this->actingAs($company, 'company')
        ->actingAs($companyUser, 'company_user')
        ->from(route('company.user.security.edit'))
        ->put(route('company.user.password.update'), [
            'current_password' => 'password',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'DifferentSecurePassword123!',
        ]);

    $response->assertRedirect(route('company.user.security.edit'))->assertSessionHasErrors(['password']);
    expect($companyUser->fresh()->password)->toBe($originalPasswordHash);
});

it('redirects guests to the company portal before showing password settings', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($company, 'company')->get(route('company.user.security.edit'));

    $response->assertRedirect(route('company.portal'));
});
