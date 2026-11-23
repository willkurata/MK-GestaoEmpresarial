<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Payment;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;

it('reports revenue by method and purpose without counting credit usage twice', function () {
    $this->travelTo('2026-11-20 18:00:00');
    $manager = Attendant::factory()->manager()->create();
    $base = ['store_id' => $manager->store_id, 'received_by' => $manager->id, 'paid_at' => now()];
    Payment::factory()->create([...$base, 'purpose' => 'sessao', 'method' => 'pix', 'amount' => '6.00']);
    Payment::factory()->create([...$base, 'purpose' => 'sessao', 'method' => 'dinheiro', 'amount' => '3.50']);
    Payment::factory()->create([...$base, 'purpose' => 'creditos', 'method' => 'cartao', 'amount' => '20.00']);
    Payment::factory()->create([...$base, 'purpose' => 'sessao', 'method' => 'creditos', 'amount' => '5.00']);
    Payment::factory()->create([...$base, 'method' => 'pix', 'amount' => '99.00', 'paid_at' => now()->subDays(3)]);
    Payment::factory()->create(['method' => 'pix', 'amount' => '77.00', 'paid_at' => now()]);

    $this->getJson(route('cybercoffe.reports.revenue', ['from' => '2026-11-20', 'to' => '2026-11-20']), cyberCoffeAuth($manager))
        ->assertOk()
        ->assertJsonPath('revenue_total', '29.50')
        ->assertJsonPath('payments_count', 3)
        ->assertJsonPath('by_method.pix', '6.00')
        ->assertJsonPath('by_method.dinheiro', '3.50')
        ->assertJsonPath('by_method.cartao', '20.00')
        ->assertJsonPath('by_purpose.sessao', '9.50')
        ->assertJsonPath('by_purpose.creditos', '20.00')
        ->assertJsonPath('credits_consumed', '5.00');
});

it('reports occupied minutes per station clipped to the period', function () {
    $this->travelTo('2026-11-21 12:00:00');
    $manager = Attendant::factory()->manager()->create();
    $pc1 = Station::factory()->for($manager->store)->create(['name' => 'PC-01']);
    Station::factory()->for($manager->store)->create(['name' => 'PC-02']);
    $base = ['store_id' => $manager->store_id, 'station_id' => $pc1->id, 'opened_by' => $manager->id];
    StationSession::factory()->closedBetween('2026-11-20 23:30:00', '2026-11-21 01:00:00')->create($base);
    StationSession::factory()->create([...$base, 'started_at' => '2026-11-21 11:00:00', 'ends_at' => '2026-11-21 13:00:00']);

    $response = $this->getJson(route('cybercoffe.reports.occupancy', ['from' => '2026-11-21', 'to' => '2026-11-21']), cyberCoffeAuth($manager));

    $response->assertOk()
        ->assertJsonPath('period_minutes', 720)
        ->assertJsonPath('occupied_minutes', 120)
        ->assertJsonPath('stations.0.name', 'PC-01')
        ->assertJsonPath('stations.0.occupied_minutes', 120)
        ->assertJsonPath('stations.0.sessions_count', 2)
        ->assertJsonPath('stations.1.occupied_minutes', 0);
    expect($response->json('stations.0.occupancy_rate'))->toEqual(round(120 / 720, 4))
        ->and($response->json('occupancy_rate'))->toEqual(round(120 / 1440, 4));
});

it('validates the report period', function () {
    $manager = Attendant::factory()->manager()->create();

    $this->getJson(route('cybercoffe.reports.revenue', ['from' => '2026-11-21', 'to' => '2026-11-01']), cyberCoffeAuth($manager))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
    $this->getJson(route('cybercoffe.reports.occupancy'), cyberCoffeAuth($manager))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from', 'to']);
});

it('forbids reports to regular attendants', function () {
    $this->getJson(route('cybercoffe.reports.revenue', ['from' => '2026-11-21', 'to' => '2026-11-21']), cyberCoffeAuth(Attendant::factory()->create()))
        ->assertForbidden();
});
