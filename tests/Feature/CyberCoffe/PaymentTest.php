<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\StationSession;

function sessionDue(Attendant $attendant, string $amountDue, ?Customer $customer = null): StationSession
{
    return StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'opened_by' => $attendant->id,
        'customer_id' => $customer?->id,
        'amount_due' => $amountDue,
    ]);
}

it('registers a cash payment with change for the outstanding amount', function () {
    $attendant = Attendant::factory()->create();
    $session = sessionDue($attendant, '6.00');

    $this->postJson(route('cybercoffe.sessions.payments.store', $session->id), [
        'method' => 'dinheiro',
        'amount_received' => '10.00',
    ], cyberCoffeAuth($attendant))
        ->assertCreated()
        ->assertJsonPath('payment.amount', '6.00')
        ->assertJsonPath('payment.change_amount', '4.00')
        ->assertJsonPath('session.balance_due', '0.00');

    $this->assertDatabaseHas('cc_payments', [
        'station_session_id' => $session->id,
        'method' => 'dinheiro',
        'amount' => '6.00',
        'amount_received' => '10.00',
        'change_amount' => '4.00',
        'received_by' => $attendant->id,
    ]);
});

it('accepts partial payments by pix and card until the session is paid', function () {
    $attendant = Attendant::factory()->create();
    $session = sessionDue($attendant, '10.00');
    $headers = cyberCoffeAuth($attendant);

    $this->postJson(route('cybercoffe.sessions.payments.store', $session->id), ['method' => 'pix', 'amount' => '4.00', 'reference' => 'E123'], $headers)
        ->assertCreated()
        ->assertJsonPath('session.balance_due', '6.00');
    $this->postJson(route('cybercoffe.sessions.payments.store', $session->id), ['method' => 'cartao'], $headers)
        ->assertCreated()
        ->assertJsonPath('payment.amount', '6.00')
        ->assertJsonPath('session.balance_due', '0.00');
    $this->postJson(route('cybercoffe.sessions.payments.store', $session->id), ['method' => 'pix'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount' => 'Não há valor em aberto nesta sessão.']);
});

it('rejects payments above the outstanding amount or cash below the paid amount', function (array $payload, string $field) {
    $attendant = Attendant::factory()->create();
    $session = sessionDue($attendant, '6.00');

    $this->postJson(route('cybercoffe.sessions.payments.store', $session->id), $payload, cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
    $this->assertDatabaseCount('cc_payments', 0);
})->with([
    'above outstanding' => [['method' => 'pix', 'amount' => '6.01'], 'amount'],
    'cash below amount' => [['method' => 'dinheiro', 'amount_received' => '5.00'], 'amount_received'],
    'unknown method' => [['method' => 'cheque'], 'method'],
]);

it('sells prepaid credits and records them in the ledger', function () {
    $attendant = Attendant::factory()->create();
    $customer = Customer::factory()->for($attendant->store)->create();

    $this->postJson(route('cybercoffe.customers.credits.purchase', $customer->id), ['method' => 'pix', 'amount' => '20.00'], cyberCoffeAuth($attendant))
        ->assertCreated()
        ->assertJsonPath('payment.purpose', 'creditos')
        ->assertJsonPath('balance', '20.00');

    $this->assertDatabaseHas('cc_credit_entries', ['customer_id' => $customer->id, 'type' => 'compra', 'amount' => '20.00']);
});

it('does not sell credits paid with credits', function () {
    $attendant = Attendant::factory()->create();
    $customer = Customer::factory()->for($attendant->store)->create();

    $this->postJson(route('cybercoffe.customers.credits.purchase', $customer->id), ['method' => 'creditos', 'amount' => '20.00'], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);
});

it('pays a session with the customer credits and debits the ledger', function () {
    $attendant = Attendant::factory()->create();
    $customer = Customer::factory()->for($attendant->store)->create();
    $headers = cyberCoffeAuth($attendant);
    $this->postJson(route('cybercoffe.customers.credits.purchase', $customer->id), ['method' => 'dinheiro', 'amount' => '10.00'], $headers)->assertCreated();
    $session = sessionDue($attendant, '6.00', $customer);

    $this->postJson(route('cybercoffe.sessions.payments.store', $session->id), ['method' => 'creditos'], $headers)
        ->assertCreated()
        ->assertJsonPath('session.balance_due', '0.00');

    $this->getJson(route('cybercoffe.customers.credits', $customer->id), $headers)
        ->assertOk()
        ->assertJsonPath('balance', '4.00');
    $this->assertDatabaseHas('cc_credit_entries', ['customer_id' => $customer->id, 'type' => 'consumo', 'amount' => '-6.00']);
});

it('refuses to pay with credits when the balance is insufficient or there is no customer', function (bool $withCustomer, string $message) {
    $attendant = Attendant::factory()->create();
    $customer = Customer::factory()->for($attendant->store)->create();
    $session = sessionDue($attendant, '6.00', $withCustomer ? $customer : null);

    $this->postJson(route('cybercoffe.sessions.payments.store', $session->id), ['method' => 'creditos'], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method' => $message]);
    $this->assertDatabaseCount('cc_payments', 0);
    $this->assertDatabaseCount('cc_credit_entries', 0);
})->with([
    'insufficient balance' => [true, 'Saldo de créditos insuficiente.'],
    'no customer' => [false, 'Pagamento com créditos exige um cliente vinculado à sessão.'],
]);

it('returns 404 when selling credits to a customer of another store', function () {
    $attendant = Attendant::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $this->postJson(route('cybercoffe.customers.credits.purchase', $otherCustomer->id), ['method' => 'pix', 'amount' => '20.00'], cyberCoffeAuth($attendant))
        ->assertNotFound();
    $this->assertDatabaseCount('cc_payments', 0);
});

it('stores the customer document encrypted and never returns it', function () {
    $attendant = Attendant::factory()->create();

    $response = $this->postJson(route('cybercoffe.customers.store'), [
        'name' => 'Maria',
        'phone' => '11 97777-6666',
        'document' => '12345678900',
    ], cyberCoffeAuth($attendant));

    $response->assertCreated()->assertJsonPath('data.has_document', true)->assertJsonMissingPath('data.document');
    $customer = Customer::firstOrFail();
    expect($customer->getRawOriginal('document'))->not->toBe('12345678900')
        ->and($customer->document)->toBe('12345678900');
});
