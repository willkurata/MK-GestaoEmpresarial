<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 15:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  list<array{product: ?Product, quantity: int, unit_price: string, unit_cost: ?string}>  $items
 */
function recordDashboardSale(Company $company, CompanyUser $operator, array $items, string $method, string $occurredAt, string $discount = '0.00', string $status = 'finalizada'): Sale
{
    $register = $company->cashRegisters()->firstOrCreate([], ['name' => 'Caixa principal', 'status' => 'ativo']);
    $session = $register->sessions()->create([
        'company_id' => $company->id,
        'company_user_id' => $operator->id,
        'opened_at' => $occurredAt,
        'opening_amount' => '0.00',
        'status' => 'fechado',
    ]);
    $subtotal = collect($items)->sum(fn (array $item): float => $item['quantity'] * (float) $item['unit_price']);
    $total = $subtotal - (float) $discount;
    $sale = $company->sales()->create([
        'company_user_id' => $operator->id,
        'cash_register_id' => $register->id,
        'cash_session_id' => $session->id,
        'occurred_at' => $occurredAt,
        'status' => $status,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
    ]);
    $sale->payment()->create([
        'company_id' => $company->id,
        'method' => $method,
        'amount' => $total,
        'status' => $status === 'estornada' ? 'estornado' : 'confirmado',
        'paid_at' => $occurredAt,
    ]);

    foreach ($items as $item) {
        $sale->items()->create([
            'company_id' => $company->id,
            'product_id' => $item['product']?->id,
            'product_name' => $item['product']?->name ?? 'Item avulso',
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'unit_cost' => $item['unit_cost'],
        ]);
    }

    return $sale;
}

it('shows the accountant dashboard with sales, margin, expenses, products and cash flow for the company only', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();
    $operator = CompanyUser::factory()->for($company)->administrator()->create(['name' => 'Ana']);
    $coffee = Product::factory()->for($company)->create(['name' => 'Café', 'sale_price' => '10.00', 'cost' => '4.00']);
    $cake = Product::factory()->for($company)->create(['name' => 'Bolo', 'sale_price' => '20.00', 'cost' => null]);
    Product::factory()->for($company)->create(['name' => 'Suco parado', 'cost' => '2.00']);

    recordDashboardSale($company, $operator, [
        ['product' => $coffee, 'quantity' => 3, 'unit_price' => '10.00', 'unit_cost' => '4.00'],
    ], 'Pix', '2026-10-05 12:00:00');
    recordDashboardSale($company, $operator, [
        ['product' => $cake, 'quantity' => 1, 'unit_price' => '20.00', 'unit_cost' => null],
    ], 'Dinheiro', '2026-10-06 12:00:00', '5.00');
    $refunded = recordDashboardSale($company, $operator, [
        ['product' => $coffee, 'quantity' => 1, 'unit_price' => '10.00', 'unit_cost' => '4.00'],
    ], 'Pix', '2026-10-07 12:00:00', '0.00', 'estornada');
    Refund::query()->create([
        'company_id' => $company->id,
        'payment_id' => $refunded->payment->id,
        'company_user_id' => $operator->id,
        'amount' => '10.00',
        'method' => 'Pix',
        'reason' => 'Pedido errado',
        'refunded_at' => '2026-10-07 13:00:00',
    ]);
    recordDashboardSale($company, $operator, [
        ['product' => $coffee, 'quantity' => 2, 'unit_price' => '10.00', 'unit_cost' => '4.00'],
    ], 'Pix', '2026-09-15 12:00:00');
    $company->expenses()->create(['description' => 'Aluguel', 'amount' => '20.00', 'type' => 'fixa', 'expense_date' => '2026-10-01']);
    $company->expenses()->create(['description' => 'Embalagens', 'amount' => '5.00', 'type' => 'variavel', 'expense_date' => '2026-10-02']);

    $otherCompany = Company::factory()->create();
    $otherOperator = CompanyUser::factory()->for($otherCompany)->create();
    $otherProduct = Product::factory()->for($otherCompany)->create(['name' => 'Produto de outra empresa']);
    recordDashboardSale($otherCompany, $otherOperator, [
        ['product' => $otherProduct, 'quantity' => 50, 'unit_price' => '100.00', 'unit_cost' => '1.00'],
    ], 'Pix', '2026-10-05 12:00:00');
    $otherCompany->expenses()->create(['description' => 'Aluguel', 'amount' => '999.00', 'type' => 'fixa', 'expense_date' => '2026-10-01']);

    $this->actingAs($superAdmin)
        ->get(route('mk.companies.show', ['company' => $company, 'period' => 'this_month']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mk/Companies/Show')
            ->where('dashboard.filters.startDate', '2026-10-01')
            ->where('dashboard.filters.endDate', '2026-10-20')
            ->where('dashboard.sales.count', 2)
            ->where('dashboard.sales.grossTotal', '50.00')
            ->where('dashboard.sales.discountTotal', '5.00')
            ->where('dashboard.sales.netTotal', '45.00')
            ->where('dashboard.sales.averageTicket', '22.50')
            ->where('dashboard.sales.refundedCount', 1)
            ->where('dashboard.sales.byPaymentMethod.pix', '30.00')
            ->where('dashboard.sales.byPaymentMethod.dinheiro', '15.00')
            ->where('dashboard.sales.byOperator.0.operator', 'Ana')
            ->where('dashboard.margin.revenueWithCost', '30.00')
            ->where('dashboard.margin.costOfGoodsSold', '12.00')
            ->where('dashboard.margin.grossProfit', '18.00')
            ->where('dashboard.margin.grossMargin', 60)
            ->where('dashboard.margin.costCoverage', 60)
            ->where('dashboard.result.revenueMinusExpenses', '20.00')
            ->where('dashboard.result.operatingResult', null)
            ->where('dashboard.comparison.netTotal', 125)
            ->where('dashboard.expenses.total', '25.00')
            ->where('dashboard.expenses.fixedTotal', '20.00')
            ->where('dashboard.expenses.topExpenses.0.description', 'Aluguel')
            ->where('dashboard.products.catalogCount', 3)
            ->where('dashboard.products.activeWithoutCostCount', 1)
            ->where('dashboard.products.bestSellers.0.productName', 'Café')
            ->where('dashboard.products.bestSellers.0.quantity', 3)
            ->where('dashboard.products.activeWithoutSalesCount', 1)
            ->where('dashboard.products.activeWithoutSales.0', 'Suco parado')
            ->where('dashboard.cashFlow.inflow', '45.00')
            ->where('dashboard.cashFlow.refundOutflow', '10.00')
            ->where('dashboard.cashFlow.netInflow', '35.00')
            ->has('dashboard.monthlyTrend', 6)
            ->where('dashboard.monthlyTrend.5.month', '2026-10')
            ->where('dashboard.monthlyTrend.5.revenue', '45.00')
            ->where('dashboard.monthlyTrend.4.revenue', '20.00')
            ->where('dashboard.insights', fn ($insights) => collect($insights)->pluck('title')->contains('Custos incompletos')
                && collect($insights)->pluck('title')->contains('Muitos estornos')));
});

it('shows empty metrics instead of invented values for a company without data', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('mk.companies.show', $company))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.period', 'this_month')
            ->where('dashboard.sales.count', 0)
            ->where('dashboard.sales.averageTicket', '0.00')
            ->where('dashboard.margin.grossMargin', null)
            ->where('dashboard.margin.costCoverage', null)
            ->where('dashboard.comparison.netTotal', null)
            ->where('dashboard.insights.0.title', 'Sem vendas finalizadas no período'));
});

it('rejects an invalid custom period', function () {
    $superAdmin = User::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('mk.companies.show', ['company' => $company, 'period' => 'custom', 'start_date' => '2026-10-10', 'end_date' => '2026-10-01']))
        ->assertSessionHasErrors('end_date');
});

it('keeps the company dashboard restricted to MK administrators', function () {
    $company = Company::factory()->create();

    $this->get(route('mk.companies.show', $company))->assertRedirect(route('login'));
});
