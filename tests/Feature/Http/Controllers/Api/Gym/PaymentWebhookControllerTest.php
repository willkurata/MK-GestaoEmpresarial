<?php

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use App\Models\Gym\GymWebhookEvent;
use Illuminate\Testing\TestResponse;

function sendGymWebhook(array $payload, ?string $secret = 'segredo-webhook'): TestResponse
{
    $body = json_encode($payload);
    $signature = $secret === null ? 'assinatura-falsa' : hash_hmac('sha256', $body, $secret);

    return test()->call('POST', route('academia.webhooks.payments'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_ACADEMIA_SIGNATURE' => $signature,
    ], $body);
}

function confirmedPaymentPayload(GymInvoice $invoice, array $overrides = []): array
{
    return [
        'event_id' => 'evt_1',
        'type' => 'payment.confirmed',
        'charge_reference' => $invoice->gateway_reference,
        'payment_id' => 'pay_1',
        'method' => 'pix',
        'amount' => $invoice->amount,
        'occurred_at' => '2026-11-23T10:00:00-03:00',
        ...$overrides,
    ];
}

beforeEach(function () {
    config(['academia.payments.webhook_secret' => 'segredo-webhook']);
});

it('rejects a webhook with an invalid signature', function () {
    $invoice = GymInvoice::factory()->create(['gateway_reference' => 'sandbox_abc']);

    sendGymWebhook(confirmedPaymentPayload($invoice), secret: null)->assertUnauthorized();

    expect($invoice->refresh()->status)->toBe(GymInvoice::STATUS_PENDING);
});

it('rejects every webhook while no secret is configured', function () {
    config(['academia.payments.webhook_secret' => null]);
    $invoice = GymInvoice::factory()->create(['gateway_reference' => 'sandbox_abc']);

    sendGymWebhook(confirmedPaymentPayload($invoice), secret: '')->assertUnauthorized();
});

it('settles the invoice when the gateway confirms the payment', function () {
    $invoice = GymInvoice::factory()->create(['gateway_reference' => 'sandbox_abc', 'amount' => '99.90']);

    sendGymWebhook(confirmedPaymentPayload($invoice))->assertOk()->assertJsonPath('status', 'processado');

    expect($invoice->refresh()->status)->toBe(GymInvoice::STATUS_PAID);
    $this->assertDatabaseHas('gym_payments', ['gym_invoice_id' => $invoice->id, 'source' => 'gateway', 'gateway_payment_id' => 'pay_1']);
});

it('processes a repeated event only once', function () {
    $invoice = GymInvoice::factory()->create(['gateway_reference' => 'sandbox_abc']);

    sendGymWebhook(confirmedPaymentPayload($invoice))->assertOk();
    sendGymWebhook(confirmedPaymentPayload($invoice))->assertOk()->assertJsonPath('status', 'duplicado');

    expect(GymPayment::query()->count())->toBe(1);
});

it('refunds the payment when the gateway reports a chargeback', function () {
    $invoice = GymInvoice::factory()->create(['gateway_reference' => 'sandbox_abc']);
    sendGymWebhook(confirmedPaymentPayload($invoice))->assertOk();

    sendGymWebhook(confirmedPaymentPayload($invoice, ['event_id' => 'evt_2', 'type' => 'payment.refunded']))->assertOk();

    expect(GymPayment::query()->sole()->status)->toBe(GymPayment::STATUS_REFUNDED)
        ->and($invoice->refresh()->status)->toBe(GymInvoice::STATUS_PENDING);
});

it('does not mark a failed event as processed so the gateway can retry it', function () {
    $invoice = GymInvoice::factory()->create(['gateway_reference' => 'sandbox_abc']);

    sendGymWebhook(confirmedPaymentPayload($invoice, ['charge_reference' => 'desconhecida']))->assertNotFound();

    expect(GymWebhookEvent::query()->count())->toBe(0);
    sendGymWebhook(confirmedPaymentPayload($invoice))->assertOk()->assertJsonPath('status', 'processado');
});
