<?php

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymSubscription;

it('generates the invoice of subscriptions due within the lead time and advances the billing date', function () {
    $this->travelTo('2026-11-23 03:00:00');
    config(['academia.invoice_lead_days' => 5]);
    $dueSoon = GymSubscription::factory()->create(['next_billing_on' => '2026-11-26', 'price' => '89.90']);
    $notYet = GymSubscription::factory()->create(['next_billing_on' => '2026-12-10']);

    $this->artisan('academia:generate-invoices')->expectsOutput('1 mensalidade(s) gerada(s).')->assertSuccessful();

    $invoice = $dueSoon->invoices()->sole();
    expect($invoice->amount)->toBe('89.90')
        ->and($invoice->due_on->toDateString())->toBe('2026-11-26')
        ->and($invoice->period_end->toDateString())->toBe('2026-12-25')
        ->and($invoice->gateway_reference)->not->toBeNull()
        ->and($dueSoon->refresh()->next_billing_on->toDateString())->toBe('2026-12-26')
        ->and($notYet->invoices()->count())->toBe(0);
});

it('does not bill the same period twice when run again', function () {
    $this->travelTo('2026-11-23 03:00:00');
    $subscription = GymSubscription::factory()->create(['next_billing_on' => '2026-11-24']);

    $this->artisan('academia:generate-invoices')->assertSuccessful();
    $this->artisan('academia:generate-invoices')->assertSuccessful();

    expect($subscription->invoices()->count())->toBe(1);
});

it('catches up every missed period and respects the plan interval', function () {
    $this->travelTo('2026-11-23 03:00:00');
    config(['academia.invoice_lead_days' => 0]);
    $subscription = GymSubscription::factory()->create(['next_billing_on' => '2026-05-23', 'billing_interval_months' => 3]);

    $this->artisan('academia:generate-invoices')->assertSuccessful();

    expect($subscription->invoices()->orderBy('due_on')->pluck('due_on')->map->toDateString()->all())
        ->toBe(['2026-05-23', '2026-08-23', '2026-11-23'])
        ->and($subscription->refresh()->next_billing_on->toDateString())->toBe('2027-02-23');
});

it('skips cancelled subscriptions', function () {
    $this->travelTo('2026-11-23 03:00:00');
    GymSubscription::factory()->canceled()->create(['next_billing_on' => '2026-11-23']);

    $this->artisan('academia:generate-invoices')->assertSuccessful();

    expect(GymInvoice::query()->count())->toBe(0);
});

it('keeps the billing day stable at the end of the month', function () {
    $this->travelTo('2027-01-31 03:00:00');
    config(['academia.invoice_lead_days' => 0]);
    $subscription = GymSubscription::factory()->create(['next_billing_on' => '2027-01-31']);

    $this->artisan('academia:generate-invoices')->assertSuccessful();

    expect($subscription->refresh()->next_billing_on->toDateString())->toBe('2027-02-28');
});
