<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;
use App\Models\CyberCoffe\Store;

it('lets a manager register a station in their store', function () {
    $manager = Attendant::factory()->manager()->create();

    $this->postJson(route('cybercoffe.stations.store'), ['name' => 'PC-01', 'type' => 'pc'], cyberCoffeAuth($manager))
        ->assertCreated()
        ->assertJsonPath('data.status', 'livre');

    $this->assertDatabaseHas('cc_stations', ['store_id' => $manager->store_id, 'name' => 'PC-01', 'status' => 'livre']);
});

it('rejects a duplicated station name in the same store but allows it in another store', function () {
    $manager = Attendant::factory()->manager()->create();
    Station::factory()->for($manager->store)->create(['name' => 'PC-01']);
    Station::factory()->create(['name' => 'PC-02']);

    $this->postJson(route('cybercoffe.stations.store'), ['name' => 'PC-01', 'type' => 'pc'], cyberCoffeAuth($manager))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
    $this->postJson(route('cybercoffe.stations.store'), ['name' => 'PC-02', 'type' => 'pc'], cyberCoffeAuth($manager))
        ->assertCreated();
});

it('lists only the store stations with the remaining time of active sessions', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create(['name' => 'PC-01', 'status' => 'ocupada']);
    StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'station_id' => $station->id,
        'opened_by' => $attendant->id,
        'started_at' => now()->subMinutes(20),
        'ends_at' => now()->addMinutes(40),
    ]);
    Station::factory()->create(['name' => 'PC-OUTRA-LOJA']);

    $response = $this->getJson(route('cybercoffe.stations.index'), cyberCoffeAuth($attendant));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'PC-01')
        ->assertJsonPath('data.0.active_session.remaining_seconds', 2400)
        ->assertJsonPath('data.0.active_session.elapsed_seconds', 1200);
});

it('returns 404 for a station of another store', function () {
    $attendant = Attendant::factory()->create();
    $otherStation = Station::factory()->create();

    $this->getJson(route('cybercoffe.stations.show', $otherStation->id), cyberCoffeAuth($attendant))->assertNotFound();
    $this->patchJson(route('cybercoffe.stations.status', $otherStation->id), ['status' => 'manutencao'], cyberCoffeAuth($attendant))->assertNotFound();
    expect($otherStation->fresh()->status)->toBe('livre');
});

it('puts a free station in maintenance and back', function () {
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();

    $this->patchJson(route('cybercoffe.stations.status', $station->id), ['status' => 'manutencao'], cyberCoffeAuth($attendant))
        ->assertOk()
        ->assertJsonPath('data.status', 'manutencao');
    $this->patchJson(route('cybercoffe.stations.status', $station->id), ['status' => 'livre'], cyberCoffeAuth($attendant))
        ->assertOk()
        ->assertJsonPath('data.status', 'livre');
});

it('does not change the status of an occupied station', function (string $status) {
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create(['status' => 'ocupada']);

    $this->patchJson(route('cybercoffe.stations.status', $station->id), ['status' => $status], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => 'Encerre a sessão ativa antes de alterar o status da estação.']);
    expect($station->fresh()->status)->toBe('ocupada');
})->with(['livre', 'manutencao']);

it('does not allow setting a station as occupied manually', function () {
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();

    $this->patchJson(route('cybercoffe.stations.status', $station->id), ['status' => 'ocupada'], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('issues an agent token that reports the lock state of the station', function () {
    $this->freezeSecond();
    $manager = Attendant::factory()->manager()->create();
    $station = Station::factory()->for($manager->store)->create();

    $token = $this->postJson(route('cybercoffe.stations.agent-token', $station->id), [], cyberCoffeAuth($manager))
        ->assertCreated()
        ->json('token');
    $agentHeaders = ['Authorization' => "Bearer {$token}"];

    $this->postJson(route('cybercoffe.agent.heartbeat'), [], $agentHeaders)
        ->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonPath('session', null);
    expect($station->fresh()->last_seen_at->equalTo(now()))->toBeTrue();

    $station->update(['status' => 'ocupada']);
    StationSession::factory()->create([
        'store_id' => $station->store_id,
        'station_id' => $station->id,
        'opened_by' => $manager->id,
        'started_at' => now(),
        'ends_at' => now()->addMinutes(30),
    ]);

    $this->getJson(route('cybercoffe.agent.session'), $agentHeaders)
        ->assertOk()
        ->assertJsonPath('locked', false)
        ->assertJsonPath('session.remaining_seconds', 1800);

    $this->travel(31)->minutes();

    $this->getJson(route('cybercoffe.agent.session'), $agentHeaders)
        ->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonPath('session.expired', true);
});

it('revokes the previous agent token when a new one is issued', function () {
    $manager = Attendant::factory()->manager()->create();
    $station = Station::factory()->for($manager->store)->create();
    $oldHeaders = cyberCoffeAuth($station);

    $this->postJson(route('cybercoffe.stations.agent-token', $station->id), [], cyberCoffeAuth($manager))->assertCreated();

    $this->getJson(route('cybercoffe.agent.session'), $oldHeaders)->assertUnauthorized();
});

it('does not let a station token use attendant endpoints', function () {
    $station = Station::factory()->for(Store::factory())->create();

    $this->getJson(route('cybercoffe.stations.index'), cyberCoffeAuth($station))->assertUnauthorized();
});
