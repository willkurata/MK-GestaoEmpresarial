<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Plan;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;

it('checks in a package session, charges the plan and occupies the station', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();
    $customer = Customer::factory()->for($attendant->store)->create();
    $plan = Plan::factory()->for($attendant->store)->create(['name' => '1 hora', 'price' => '6.00', 'minutes' => 60]);

    $response = $this->postJson(route('cybercoffe.sessions.check-in'), [
        'station_id' => $station->id,
        'plan_id' => $plan->id,
        'customer_id' => $customer->id,
    ], cyberCoffeAuth($attendant));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'ativa')
        ->assertJsonPath('data.remaining_seconds', 3600)
        ->assertJsonPath('data.amount_due', '6.00')
        ->assertJsonPath('data.balance_due', '6.00')
        ->assertJsonPath('data.customer.id', $customer->id);
    expect($station->fresh()->status)->toBe('ocupada');
    $this->assertDatabaseHas('cc_session_charges', ['plan_id' => $plan->id, 'minutes' => 60, 'amount' => '6.00']);
});

it('refuses to check in on a station that is not free', function (string $status) {
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create(['status' => $status]);
    $plan = Plan::factory()->for($attendant->store)->create();

    $this->postJson(route('cybercoffe.sessions.check-in'), ['station_id' => $station->id, 'plan_id' => $plan->id], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['station_id' => 'A estação não está livre.']);
    $this->assertDatabaseCount('cc_station_sessions', 0);
})->with(['ocupada', 'manutencao']);

it('returns 404 when check-in uses a station, plan or customer of another store', function (string $foreign) {
    $attendant = Attendant::factory()->create();
    $ids = [
        'station_id' => Station::factory()->for($attendant->store)->create()->id,
        'plan_id' => Plan::factory()->for($attendant->store)->create()->id,
    ];
    $ids[$foreign] = match ($foreign) {
        'station_id' => Station::factory()->create()->id,
        'plan_id' => Plan::factory()->create()->id,
        'customer_id' => Customer::factory()->create()->id,
    };

    $this->postJson(route('cybercoffe.sessions.check-in'), $ids, cyberCoffeAuth($attendant))->assertNotFound();
    $this->assertDatabaseCount('cc_station_sessions', 0);
})->with(['station_id', 'plan_id', 'customer_id']);

it('refuses an inactive plan at check-in', function () {
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();
    $plan = Plan::factory()->for($attendant->store)->inactive()->create();

    $this->postJson(route('cybercoffe.sessions.check-in'), ['station_id' => $station->id, 'plan_id' => $plan->id], cyberCoffeAuth($attendant))
        ->assertNotFound();
});

it('adds package time to the current end while there is time left', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $plan = Plan::factory()->for($attendant->store)->create(['price' => '3.50', 'minutes' => 30]);
    $session = StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'opened_by' => $attendant->id,
        'started_at' => now()->subMinutes(50),
        'ends_at' => now()->addMinutes(10),
        'amount_due' => '6.00',
    ]);

    $this->postJson(route('cybercoffe.sessions.extend', $session->id), ['plan_id' => $plan->id], cyberCoffeAuth($attendant))
        ->assertOk()
        ->assertJsonPath('data.remaining_seconds', 40 * 60)
        ->assertJsonPath('data.amount_due', '9.50');
});

it('counts added package time from now when the session had already expired', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $plan = Plan::factory()->for($attendant->store)->create(['minutes' => 30]);
    $session = StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'opened_by' => $attendant->id,
        'started_at' => now()->subMinutes(80),
        'ends_at' => now()->subMinutes(20),
    ]);

    $this->postJson(route('cybercoffe.sessions.extend', $session->id), ['plan_id' => $plan->id], cyberCoffeAuth($attendant))
        ->assertOk()
        ->assertJsonPath('data.remaining_seconds', 30 * 60);
});

it('refuses to extend with an open plan', function () {
    $attendant = Attendant::factory()->create();
    $plan = Plan::factory()->for($attendant->store)->open()->create();
    $session = StationSession::factory()->create(['store_id' => $attendant->store_id, 'opened_by' => $attendant->id]);

    $this->postJson(route('cybercoffe.sessions.extend', $session->id), ['plan_id' => $plan->id], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);
});

it('bills an open session per started minute at check-out and frees the station', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();
    $plan = Plan::factory()->for($attendant->store)->open('6.00')->create();
    $headers = cyberCoffeAuth($attendant);

    $sessionId = $this->postJson(route('cybercoffe.sessions.check-in'), ['station_id' => $station->id, 'plan_id' => $plan->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.remaining_seconds', null)
        ->json('data.id');

    $this->travel(61 * 60 + 5)->seconds();

    $this->postJson(route('cybercoffe.sessions.check-out', $sessionId), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'encerrada')
        ->assertJsonPath('data.amount_due', '6.20')
        ->assertJsonPath('data.balance_due', '6.20');
    expect($station->fresh()->status)->toBe('livre');
    $this->assertDatabaseHas('cc_station_sessions', ['id' => $sessionId, 'closed_by' => $attendant->id]);
    $this->assertDatabaseHas('cc_session_charges', ['station_session_id' => $sessionId, 'minutes' => 62, 'amount' => '6.20']);
});

it('keeps the snapshotted hourly rate even if the plan is replaced', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();
    $plan = Plan::factory()->for($attendant->store)->open('6.00')->create();
    $headers = cyberCoffeAuth($attendant);
    $sessionId = $this->postJson(route('cybercoffe.sessions.check-in'), ['station_id' => $station->id, 'plan_id' => $plan->id], $headers)->json('data.id');

    $plan->forceFill(['hourly_rate' => '12.00'])->save();
    $this->travel(30)->minutes();

    $this->postJson(route('cybercoffe.sessions.check-out', $sessionId), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.amount_due', '3.00');
});

it('refuses to check out a session twice', function () {
    $attendant = Attendant::factory()->create();
    $session = StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'opened_by' => $attendant->id,
        'status' => 'encerrada',
        'closed_at' => now(),
    ]);

    $this->postJson(route('cybercoffe.sessions.check-out', $session->id), [], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session' => 'A sessão já foi encerrada.']);
});

it('returns 404 for a session of another store', function () {
    $attendant = Attendant::factory()->create();
    $otherSession = StationSession::factory()->create();

    $this->getJson(route('cybercoffe.sessions.show', $otherSession->id), cyberCoffeAuth($attendant))->assertNotFound();
    $this->postJson(route('cybercoffe.sessions.check-out', $otherSession->id), [], cyberCoffeAuth($attendant))->assertNotFound();
    expect($otherSession->fresh()->status)->toBe('ativa');
});

it('shows the customer only their own current session', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $customer = Customer::factory()->for($attendant->store)->create();
    $otherCustomer = Customer::factory()->for($attendant->store)->create();
    $session = StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'customer_id' => $customer->id,
        'opened_by' => $attendant->id,
        'started_at' => now(),
        'ends_at' => now()->addMinutes(15),
    ]);

    $this->getJson(route('cybercoffe.me.session'), cyberCoffeAuth($customer))
        ->assertOk()
        ->assertJsonPath('data.id', $session->id)
        ->assertJsonPath('data.remaining_seconds', 900);
    $this->getJson(route('cybercoffe.me.session'), cyberCoffeAuth($otherCustomer))
        ->assertOk()
        ->assertJsonPath('data', null);
});
