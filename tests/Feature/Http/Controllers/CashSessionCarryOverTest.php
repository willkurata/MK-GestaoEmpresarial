<?php

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // 10:00 in São Paulo; yesterday's shift opened at 18:00 local time.
    Carbon::setTestNow(Carbon::parse('2026-11-24 13:00:00', 'UTC'));
});

/**
 * Open shift started yesterday evening with R$ 100 and a R$ 30 cash sale.
 *
 * @return array{0: Company, 1: CompanyUser, 2: CashSession}
 */
function previousDayShift(?CompanyUser $operator = null): array
{
    $company = $operator?->company ?? Company::factory()->create();
    $operator ??= CompanyUser::factory()->for($company)->create();
    $register = CashRegister::factory()->for($company)->create(['status' => 'ativo']);
    $session = CashSession::factory()->for($company)->for($register)->create([
        'company_user_id' => $operator->id,
        'opened_at' => Carbon::parse('2026-11-23 21:00:00', 'UTC'),
        'opening_amount' => '100.00',
    ]);
    $company->cashMovements()->create([
        'cash_session_id' => $session->id,
        'company_user_id' => $operator->id,
        'type' => 'venda',
        'method' => 'Dinheiro',
        'amount' => '30.00',
        'description' => 'Venda #1',
        'occurred_at' => Carbon::parse('2026-11-23 22:00:00', 'UTC'),
    ]);

    return [$company, $operator, $session];
}

it('treats a shift opened late last night local time as a previous-day shift', function () {
    [, , $session] = previousDayShift();
    $sameDay = CashSession::factory()->make(['opened_at' => Carbon::parse('2026-11-24 03:30:00', 'UTC'), 'status' => 'aberto']);

    expect($session->needsCarryOverDecision())->toBeTrue()
        ->and($sameDay->needsCarryOverDecision())->toBeFalse();
});

it('offers the previous-day decision on the cashier page and blocks new sales until it is made', function () {
    [$company, $operator, $session] = previousDayShift();
    $product = Product::factory()->for($company)->create(['status' => 'ativo']);
    $this->actingAs($company, 'company')->actingAs($operator, 'company_user');

    $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page
        ->where('previousDaySession.id', $session->id)
        ->where('previousDaySession.expectedCash', '130.00')
        ->where('previousDaySession.canContinue', true)
        ->where('canWithdraw', false));

    $this->post(route('pos.sales.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'discount_type' => 'none',
        'discount_value' => '0',
        'payment_method' => 'Pix',
    ])->assertUnprocessable();
    $this->assertDatabaseCount('sales', 0);
});

it('lets the operator keep working in the same shift for today only', function () {
    [$company, $operator, $session] = previousDayShift();
    $this->actingAs($company, 'company')->actingAs($operator, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), ['action' => 'continue'])
        ->assertRedirect(route('pos.index'));

    expect($session->refresh()->status)->toBe('aberto')
        ->and($session->needsCarryOverDecision())->toBeFalse()
        ->and($session->needsCarryOverDecision(now()->addDay()))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'cash_session.continued', 'subject_id' => $session->id]);
});

it('closes the counted shift and carries the counted cash to a new shift', function () {
    [$company, $operator, $session] = previousDayShift();
    $this->actingAs($company, 'company')->actingAs($operator, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), [
        'action' => 'close',
        'counted_cash' => '125.00',
        'open_new_session' => true,
    ])->assertRedirect(route('pos.index'));

    $session->refresh();
    expect($session->status)->toBe('fechado')
        ->and($session->closing_mode)->toBe(CashSession::CLOSING_COUNTED)
        ->and((string) $session->expected_amount)->toBe('130.00')
        ->and((string) $session->difference_amount)->toBe('-5.00')
        ->and($session->closed_by_company_user_id)->toBe($operator->id);

    $newSession = $session->nextSession;
    expect($newSession->status)->toBe('aberto')
        ->and((string) $newSession->opening_amount)->toBe('125.00')
        ->and((string) $newSession->transferred_amount)->toBe('125.00')
        ->and($newSession->needsCarryOverDecision())->toBeFalse();
});

it('auto-closes without a count and keeps the transfer difference visible', function () {
    [$company, $operator, $session] = previousDayShift();
    $this->actingAs($company, 'company')->actingAs($operator, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), [
        'action' => 'auto_close',
        'open_new_session' => true,
        'opening_amount' => '120.00',
    ])->assertRedirect(route('pos.index'));

    $session->refresh();
    expect($session->closing_mode)->toBe(CashSession::CLOSING_AUTOMATIC)
        ->and($session->counted_amount)->toBeNull()
        ->and($session->difference_amount)->toBeNull()
        ->and((string) $session->nextSession->transferred_amount)->toBe('130.00')
        ->and((string) $session->nextSession->opening_amount)->toBe('120.00');
});

it('requires the counted cash to close and the opening amount to auto-close with a new shift', function () {
    [$company, $operator, $session] = previousDayShift();
    $this->actingAs($company, 'company')->actingAs($operator, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), ['action' => 'close'])
        ->assertSessionHasErrors('counted_cash');
    $this->post(route('pos.previous-days.resolve', $session->id), ['action' => 'auto_close', 'open_new_session' => true])
        ->assertSessionHasErrors('opening_amount');

    expect($session->refresh()->status)->toBe('aberto');
});

it('lets a supervisor close another operator\'s forgotten shift but not continue it', function () {
    [$company, , $session] = previousDayShift();
    $supervisor = CompanyUser::factory()->for($company)->create(['role' => 'Moderador']);
    $this->actingAs($company, 'company')->actingAs($supervisor, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), ['action' => 'continue'])->assertForbidden();
    $this->post(route('pos.previous-days.resolve', $session->id), [
        'action' => 'close',
        'counted_cash' => '130.00',
        'open_new_session' => true,
    ])->assertRedirect(route('pos.index'));

    expect($session->refresh()->closed_by_company_user_id)->toBe($supervisor->id)
        ->and($session->nextSession->company_user_id)->toBe($supervisor->id);
});

it('does not let an employee resolve another operator\'s shift', function () {
    [$company, , $session] = previousDayShift();
    $employee = CompanyUser::factory()->for($company)->create(['role' => 'Funcionário']);
    $this->actingAs($company, 'company')->actingAs($employee, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), ['action' => 'auto_close'])->assertForbidden();

    expect($session->refresh()->status)->toBe('aberto');
});

it('returns not found when resolving another company\'s shift', function () {
    [, , $session] = previousDayShift();
    [$otherCompany, $otherOperator] = previousDayShift();
    $otherOperator->update(['role' => 'Administrador']);
    $this->actingAs($otherCompany, 'company')->actingAs($otherOperator, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), ['action' => 'auto_close'])->assertNotFound();

    expect($session->refresh()->status)->toBe('aberto');
});

it('rejects resolving a shift opened today', function () {
    [$company, $operator, $session] = previousDayShift();
    $session->update(['opened_at' => now()->subHour()]);
    $this->actingAs($company, 'company')->actingAs($operator, 'company_user');

    $this->post(route('pos.previous-days.resolve', $session->id), ['action' => 'auto_close'])->assertUnprocessable();
});

it('reports pending previous-day shifts and carried-over history for the company only', function () {
    [$company, $operator, $pending] = previousDayShift();
    $admin = CompanyUser::factory()->for($company)->create(['role' => 'Administrador']);
    previousDayShift(); // another company
    $this->actingAs($company, 'company')->actingAs($admin, 'company_user');

    $this->get(route('pos.previous-days.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cashier/PreviousDays')
        ->has('pendingSessions', 1)
        ->where('pendingSessions.0.id', $pending->id)
        ->where('pendingSessions.0.expectedCash', '130.00')
        ->where('summary.pendingCount', 1)
        ->has('history', 0));

    $this->post(route('pos.previous-days.resolve', $pending->id), ['action' => 'auto_close', 'open_new_session' => true, 'opening_amount' => '128.00'])
        ->assertRedirect(route('pos.index'));

    $this->get(route('pos.previous-days.index'))->assertInertia(fn (Assert $page) => $page
        ->has('pendingSessions', 0)
        ->has('history', 1)
        ->where('history.0.closingMode', CashSession::CLOSING_AUTOMATIC)
        ->where('history.0.transfer.difference', '-2.00')
        ->where('summary.autoClosedCount', 1)
        ->where('summary.transferDifferenceTotal', '-2.00'));
});

it('hides the previous-days report from employees', function () {
    [$company] = previousDayShift();
    $employee = CompanyUser::factory()->for($company)->create(['role' => 'Funcionário']);

    $this->actingAs($company, 'company')->actingAs($employee, 'company_user')
        ->get(route('pos.previous-days.index'))
        ->assertForbidden();
});
