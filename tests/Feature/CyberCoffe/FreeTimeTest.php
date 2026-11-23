<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Plan;
use App\Models\CyberCoffe\Product;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;

it('shows the running time and amount of a free-time session without an end', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $session = StationSession::factory()->open('6.00')->create([
        'store_id' => $attendant->store_id,
        'opened_by' => $attendant->id,
        'started_at' => now()->subMinutes(20)->subSeconds(10),
    ]);

    $this->getJson(route('cybercoffe.sessions.show', $session->id), cyberCoffeAuth($attendant))
        ->assertOk()
        ->assertJsonPath('data.ends_at', null)
        ->assertJsonPath('data.remaining_seconds', null)
        ->assertJsonPath('data.expired', false)
        ->assertJsonPath('data.elapsed_seconds', 20 * 60 + 10)
        ->assertJsonPath('data.billable_minutes', 21)
        ->assertJsonPath('data.current_amount', '2.10')
        ->assertJsonPath('data.projected_total', '2.10')
        ->assertJsonPath('data.amount_due', '0.00');
});

it('rounds free time up to the plan billing increment', function (int $increment, int $elapsedMinutes, int $billedMinutes, string $amount) {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();
    $plan = Plan::factory()->for($attendant->store)->open('6.00', $increment)->create();
    $headers = cyberCoffeAuth($attendant);
    $sessionId = $this->postJson(route('cybercoffe.sessions.check-in'), ['station_id' => $station->id, 'plan_id' => $plan->id], $headers)
        ->assertJsonPath('data.billing_increment_minutes', $increment)
        ->json('data.id');

    $this->travel($elapsedMinutes * 60 + 1)->seconds();

    $this->postJson(route('cybercoffe.sessions.check-out', $sessionId), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.amount_due', $amount);
    $this->assertDatabaseHas('cc_session_charges', ['station_session_id' => $sessionId, 'kind' => 'tempo', 'minutes' => $billedMinutes, 'amount' => $amount]);
})->with([
    'per minute' => [1, 16, 17, '1.70'],
    'per 15 minutes' => [15, 16, 30, '3.00'],
    'per hour' => [60, 61, 120, '12.00'],
]);

it('adds the time used on top of products already on a free-time bill at check-out', function () {
    $this->freezeSecond();
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create();
    $plan = Plan::factory()->for($attendant->store)->open('6.00')->create();
    $water = Product::factory()->for($attendant->store)->create(['name' => 'Água', 'price' => '3.00']);
    $headers = cyberCoffeAuth($attendant);
    $sessionId = $this->postJson(route('cybercoffe.sessions.check-in'), ['station_id' => $station->id, 'plan_id' => $plan->id], $headers)->json('data.id');

    $this->postJson(route('cybercoffe.sessions.items.store', $sessionId), ['product_id' => $water->id, 'quantity' => 2], $headers)
        ->assertCreated()
        ->assertJsonPath('data.amount_due', '6.00');
    $this->travel(30)->minutes();

    $this->getJson(route('cybercoffe.sessions.show', $sessionId), $headers)
        ->assertJsonPath('data.projected_total', '9.00')
        ->assertJsonPath('data.bill.time_amount', '3.00')
        ->assertJsonPath('data.bill.products_amount', '6.00');

    $this->postJson(route('cybercoffe.sessions.check-out', $sessionId), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.amount_due', '9.00')
        ->assertJsonPath('data.balance_due', '9.00')
        ->assertJsonPath('data.bill.total', '9.00')
        ->assertJsonPath('data.bill.time_amount', '3.00')
        ->assertJsonPath('data.charges.1.description', 'Tempo livre (30 min)');
});

it('rejects a billing increment on package plans and outside the allowed steps', function () {
    $manager = Attendant::factory()->manager()->create();
    $headers = cyberCoffeAuth($manager);

    $this->postJson(route('cybercoffe.plans.store'), ['name' => '1h', 'billing' => 'pacote', 'price' => '6.00', 'minutes' => 60, 'billing_increment_minutes' => 15], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_increment_minutes']);
    $this->postJson(route('cybercoffe.plans.store'), ['name' => 'Livre', 'billing' => 'avulso', 'hourly_rate' => '6.00', 'billing_increment_minutes' => 7], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_increment_minutes']);
    $this->postJson(route('cybercoffe.plans.store'), ['name' => 'Livre', 'billing' => 'avulso', 'hourly_rate' => '6.00'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.billing_increment_minutes', 1);
});
