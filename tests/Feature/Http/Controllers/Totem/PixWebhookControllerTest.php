<?php

use App\Actions\Totem\PrintOrderPayments;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Illuminate\Support\Facades\Storage;

function awaitingPaymentOrder(): PrintOrder
{
    $order = PrintOrder::factory()->for(Totem::factory()->online())->priced()->create();

    return app(PrintOrderPayments::class)->checkout($order)->refresh();
}

beforeEach(function () {
    config(['totem.pix.webhook_secret' => 'test-webhook-secret']);
});

it('queues the order for printing when a signed payment arrives', function () {
    Storage::fake('local');
    $order = awaitingPaymentOrder();
    [$body, $headers] = totemSignedPixWebhook($order->payment->external_id);

    $this->call('POST', route('totem.webhooks.pix'), [], [], [], $this->transformHeadersToServerVars($headers), $body)
        ->assertOk()
        ->assertJsonPath('data.order_status', 'queued');

    $order->refresh();
    expect($order->status)->toBe(PrintOrderStatus::Queued)
        ->and($order->payment->status)->toBe('paid')
        ->and($order->payment->paid_at)->not->toBeNull()
        ->and($order->events()->where('to_status', 'queued')->value('actor'))->toBe('payment');
});

it('returns 401 and changes nothing when the signature is wrong', function () {
    Storage::fake('local');
    $order = awaitingPaymentOrder();
    [$body, $headers] = totemSignedPixWebhook($order->payment->external_id, secret: 'attacker-secret');

    $this->call('POST', route('totem.webhooks.pix'), [], [], [], $this->transformHeadersToServerVars($headers), $body)
        ->assertUnauthorized();

    expect($order->refresh()->status)->toBe(PrintOrderStatus::AwaitingPayment)
        ->and($order->payment->status)->toBe('pending');
});

it('ignores a repeated notification for the same payment', function () {
    Storage::fake('local');
    $order = awaitingPaymentOrder();
    [$body, $headers] = totemSignedPixWebhook($order->payment->external_id);

    $this->call('POST', route('totem.webhooks.pix'), [], [], [], $this->transformHeadersToServerVars($headers), $body)->assertOk();
    $this->call('POST', route('totem.webhooks.pix'), [], [], [], $this->transformHeadersToServerVars($headers), $body)->assertOk();

    expect($order->events()->where('to_status', 'queued')->count())->toBe(1);
});

it('refunds a payment that arrives after the order expired', function () {
    Storage::fake('local');
    $order = awaitingPaymentOrder();
    $order->transitionTo(PrintOrderStatus::Expired, 'system');
    [$body, $headers] = totemSignedPixWebhook($order->payment->external_id);

    $this->call('POST', route('totem.webhooks.pix'), [], [], [], $this->transformHeadersToServerVars($headers), $body)
        ->assertOk()
        ->assertJsonPath('data.order_status', 'refunded');

    expect($order->refresh()->payment->status)->toBe('refunded')
        ->and($order->payment->refunded_at)->not->toBeNull();
});

it('refuses to run the sandbox gateway in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->postJson(route('totem.webhooks.pix'), [])->assertServerError();
});
