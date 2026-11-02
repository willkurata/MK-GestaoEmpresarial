<?php

use App\Models\Company;
use App\Models\CompanyUser;
use Inertia\Testing\AssertableInertia as Assert;

it('summarizes confirmed payments and keeps refunded and other-company sales out of totals', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $otherCompany = Company::factory()->create();
    $otherCompanyUser = CompanyUser::factory()->for($otherCompany)->create();
    $createSale = function (Company $owner, CompanyUser $operator, string $total, string $method, string $occurredAt, string $status = 'finalizada') {
        $register = $owner->cashRegisters()->firstOrCreate([], ['name' => 'Caixa principal', 'status' => 'ativo']);
        $session = $register->sessions()->create([
            'company_id' => $owner->id,
            'company_user_id' => $operator->id,
            'opened_at' => $occurredAt,
            'opening_amount' => '0.00',
            'status' => 'fechado',
        ]);
        $sale = $owner->sales()->create([
            'company_user_id' => $operator->id,
            'cash_register_id' => $register->id,
            'cash_session_id' => $session->id,
            'occurred_at' => $occurredAt,
            'status' => $status,
            'subtotal' => $total,
            'discount' => '0.00',
            'total' => $total,
        ]);
        $sale->payment()->create([
            'company_id' => $owner->id,
            'method' => $method,
            'amount' => $total,
            'status' => $status === 'estornada' ? 'estornado' : 'confirmado',
            'paid_at' => $occurredAt,
        ]);

        return $sale;
    };
    $cashSale = $createSale($company, $companyUser, '60.00', 'Dinheiro', '2026-10-10 12:00:00');
    $pixSale = $createSale($company, $companyUser, '40.00', 'pix', '2026-10-10 13:00:00');
    $refundedSale = $createSale($company, $companyUser, '25.00', 'Dinheiro', '2026-10-10 14:00:00', 'estornada');
    $createSale($company, $companyUser, '90.00', 'Pix', '2026-10-11 12:00:00');
    $createSale($otherCompany, $otherCompanyUser, '700.00', 'Pix', '2026-10-10 12:00:00');
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->get(route('reports.index', [
        'period' => 'custom',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-10',
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Reports/Index')
        ->where('filters.period', 'custom')
        ->where('summary.salesCount', 2)
        ->where('summary.salesTotal', '100.00')
        ->where('summary.cashTotal', '60.00')
        ->where('summary.pixTotal', '40.00')
        ->where('summary.refundedCount', 1)
        ->where('summary.refundedTotal', '25.00')
        ->has('recentSales', 3)
        ->where('recentSales.0.id', $refundedSale->id)
        ->where('recentSales.1.id', $pixSale->id)
        ->where('recentSales.2.id', $cashSale->id)
        ->missing('recentSales.3.id'));
});

it('rejects a custom report date range whose end is before its start', function () {
    $company = Company::factory()->create();
    $companyUser = CompanyUser::factory()->for($company)->create();
    $this->actingAs($company, 'company')->actingAs($companyUser, 'company_user');

    $response = $this->from(route('reports.index'))->get(route('reports.index', [
        'period' => 'custom',
        'start_date' => '2026-10-11',
        'end_date' => '2026-10-10',
    ]));

    $response->assertRedirect(route('reports.index'))->assertSessionHasErrors(['end_date']);
});
