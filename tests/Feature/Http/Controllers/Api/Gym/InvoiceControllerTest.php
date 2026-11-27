<?php

use App\Models\Gym\GymAuditLog;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use App\Models\Gym\GymUser;

it('settles a pending invoice at the front desk and records the operator', function (string $method) {
    $invoice = GymInvoice::factory()->create(['amount' => '99.90']);
    $manager = GymUser::factory()->for($invoice->subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.invoices.pay', $invoice->id), [
        'method' => $method,
        'amount' => '99.90',
    ])->assertCreated()->assertJsonPath('data.status', 'paga')->assertJsonPath('data.payments.0.method', $method);

    $this->assertDatabaseHas('gym_payments', [
        'gym_invoice_id' => $invoice->id,
        'gym_id' => $invoice->gym_id,
        'method' => $method,
        'source' => 'manual',
        'status' => 'confirmado',
        'recorded_by_user_id' => $manager->id,
    ]);
})->with(['pix', 'cartao', 'boleto', 'dinheiro']);

it('refuses a payment with a different amount', function () {
    $invoice = GymInvoice::factory()->create(['amount' => '99.90']);
    $manager = GymUser::factory()->for($invoice->subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.invoices.pay', $invoice->id), ['method' => 'pix', 'amount' => '50.00'])
        ->assertUnprocessable()->assertJsonValidationErrors(['amount' => 'O valor deve ser igual ao da mensalidade.']);
    expect($invoice->refresh()->status)->toBe(GymInvoice::STATUS_PENDING);
});

it('refuses to pay an invoice twice', function () {
    $invoice = GymInvoice::factory()->paid()->create();
    $manager = GymUser::factory()->for($invoice->subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.invoices.pay', $invoice->id), ['method' => 'pix', 'amount' => $invoice->amount])
        ->assertUnprocessable()->assertJsonValidationErrors(['invoice' => 'Esta mensalidade não está pendente.']);
});

it('filters overdue invoices', function () {
    $overdue = GymInvoice::factory()->overdueBy(3)->create();
    $gym = $overdue->subscription->student->gym;
    GymInvoice::factory()->create(['gym_subscription_id' => $overdue->gym_subscription_id, 'period_start' => today()->addDay(), 'due_on' => today()->addDay()]);
    $manager = GymUser::factory()->for($gym)->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.invoices.index', ['status' => 'vencida']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $overdue->id)
        ->assertJsonPath('data.0.status', 'vencida');
});

it('returns not found for an invoice of another gym', function () {
    $invoice = GymInvoice::factory()->create();
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.invoices.show', $invoice->id))->assertNotFound();
    $this->actingAs($manager, 'gym')->postJson(route('academia.invoices.pay', $invoice->id), ['method' => 'pix', 'amount' => $invoice->amount])->assertNotFound();
});

it('refunds a payment, reopens the invoice and audits the action', function () {
    $invoice = GymInvoice::factory()->paid()->create();
    $payment = $invoice->payments()->create(['gym_id' => $invoice->gym_id, 'method' => 'pix', 'amount' => $invoice->amount, 'source' => 'manual', 'paid_at' => now()]);
    $admin = GymUser::factory()->for($invoice->subscription->student->gym)->administrator()->create();

    $this->actingAs($admin, 'gym')->postJson(route('academia.payments.refund', $payment->id), ['reason' => 'Cobrança em duplicidade'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pendente')
        ->assertJsonPath('data.payments.0.status', 'estornado');

    expect($payment->refresh())
        ->status->toBe(GymPayment::STATUS_REFUNDED)
        ->refund_reason->toBe('Cobrança em duplicidade')
        ->refunded_by_user_id->toBe($admin->id);
    expect(GymAuditLog::query()->where('action', 'financeiro.pagamento_estornado')->where('subject_id', $payment->id)->exists())->toBeTrue();
});

it('refuses to refund the same payment twice', function () {
    $invoice = GymInvoice::factory()->create();
    $payment = $invoice->payments()->create(['gym_id' => $invoice->gym_id, 'method' => 'pix', 'amount' => $invoice->amount, 'source' => 'manual', 'paid_at' => now(), 'status' => 'estornado', 'refunded_at' => now()]);
    $admin = GymUser::factory()->for($invoice->subscription->student->gym)->administrator()->create();

    $this->actingAs($admin, 'gym')->postJson(route('academia.payments.refund', $payment->id), ['reason' => 'De novo'])
        ->assertUnprocessable()->assertJsonValidationErrors(['payment' => 'Este pagamento já foi estornado.']);
});

it('lets an administrator cancel a pending invoice with a reason', function () {
    $invoice = GymInvoice::factory()->create();
    $admin = GymUser::factory()->for($invoice->subscription->student->gym)->administrator()->create();

    $this->actingAs($admin, 'gym')->postJson(route('academia.invoices.cancel', $invoice->id), ['reason' => 'Bolsa concedida'])
        ->assertOk()->assertJsonPath('data.status', 'cancelada');
    expect($invoice->refresh()->cancellation_reason)->toBe('Bolsa concedida');
});

it('generates a boleto or Pix charge for a pending invoice', function (string $method, string $instructionKey) {
    $invoice = GymInvoice::factory()->create(['payment_method' => 'cartao']);
    $manager = GymUser::factory()->for($invoice->subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.invoices.charge', $invoice->id), ['method' => $method])
        ->assertOk()
        ->assertJsonPath('data.payment_method', $method)
        ->assertJsonPath('data.payment_instructions.type', $method)
        ->assertJsonPath('data.payment_instructions.environment', 'sandbox')
        ->assertJsonStructure(['data' => ['payment_instructions' => [$instructionKey]]]);

    expect($invoice->refresh()->gateway_reference)->toStartWith('sandbox_');
})->with([['pix', 'pix_copy_paste'], ['boleto', 'digitable_line']]);

it('refuses to generate a charge for a paid invoice', function () {
    $invoice = GymInvoice::factory()->paid()->create();
    $manager = GymUser::factory()->for($invoice->subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.invoices.charge', $invoice->id), ['method' => 'pix'])
        ->assertUnprocessable()->assertJsonValidationErrors(['invoice' => 'Somente mensalidades pendentes podem gerar cobrança.']);
});

it('lets a student generate the Pix of their own invoice only', function () {
    $invoice = GymInvoice::factory()->create(['payment_method' => 'boleto']);
    $student = $invoice->subscription->student;
    $account = GymUser::factory()->student()->create(['gym_id' => $student->gym_id]);
    $student->forceFill(['gym_user_id' => $account->id])->save();
    $otherInvoice = GymInvoice::factory()->create();

    $this->actingAs($account, 'gym')->postJson(route('academia.me.invoices.charge', $invoice->id), ['method' => 'pix'])
        ->assertOk()->assertJsonPath('data.payment_instructions.type', 'pix');
    $this->actingAs($account, 'gym')->postJson(route('academia.me.invoices.charge', $otherInvoice->id), ['method' => 'pix'])
        ->assertNotFound();
});
