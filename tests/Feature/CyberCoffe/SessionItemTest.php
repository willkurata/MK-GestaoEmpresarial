<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Payment;
use App\Models\CyberCoffe\Product;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;

function activeSessionFor(Attendant $attendant, array $attributes = []): StationSession
{
    return StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'opened_by' => $attendant->id,
        'amount_due' => '6.00',
        ...$attributes,
    ]);
}

it('lets a manager manage the counter catalog and attendants only list active items', function () {
    $manager = Attendant::factory()->manager()->create();
    $attendant = Attendant::factory()->for($manager->store)->create();
    $headers = cyberCoffeAuth($manager);

    $sodaId = $this->postJson(route('cybercoffe.products.store'), ['name' => 'Refrigerante lata', 'price' => '5.50'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.category', 'produto')
        ->json('data.id');
    $this->postJson(route('cybercoffe.products.store'), ['name' => 'Impressão P&B', 'category' => 'impressao', 'price' => '0.50'], $headers)
        ->assertCreated();
    $this->postJson(route('cybercoffe.products.store'), ['name' => 'Refrigerante lata', 'price' => '6.00'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
    $this->patchJson(route('cybercoffe.products.update', $sodaId), ['price' => '6.00', 'active' => false], $headers)
        ->assertOk()
        ->assertJsonPath('data.price', '6.00');

    $this->getJson(route('cybercoffe.products.index'), cyberCoffeAuth($attendant))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Impressão P&B');
    $this->postJson(route('cybercoffe.products.store'), ['name' => 'Água', 'price' => '3.00'], cyberCoffeAuth($attendant))
        ->assertForbidden();
});

it('adds a catalog product to the session bill with a snapshotted price', function () {
    $attendant = Attendant::factory()->create();
    $water = Product::factory()->for($attendant->store)->create(['name' => 'Água', 'price' => '3.00']);
    $session = activeSessionFor($attendant);

    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => $water->id, 'quantity' => 2], cyberCoffeAuth($attendant))
        ->assertCreated()
        ->assertJsonPath('data.amount_due', '12.00')
        ->assertJsonPath('data.balance_due', '12.00')
        ->assertJsonPath('data.bill.products_amount', '6.00')
        ->assertJsonPath('data.charges.0.kind', 'produto')
        ->assertJsonPath('data.charges.0.quantity', 2)
        ->assertJsonPath('data.charges.0.unit_price', '3.00')
        ->assertJsonPath('data.charges.0.manual_price', false);

    $water->update(['price' => '4.00']);

    $this->assertDatabaseHas('cc_session_charges', ['station_session_id' => $session->id, 'product_id' => $water->id, 'unit_price' => '3.00', 'amount' => '6.00', 'created_by' => $attendant->id]);
});

it('accepts a manual price for a catalog product or a loose item', function () {
    $attendant = Attendant::factory()->create();
    $snack = Product::factory()->for($attendant->store)->create(['name' => 'Coxinha', 'price' => '7.00']);
    $session = activeSessionFor($attendant, ['amount_due' => '0.00']);
    $headers = cyberCoffeAuth($attendant);

    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => $snack->id, 'unit_price' => '5.00'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.charges.0.description', 'Coxinha')
        ->assertJsonPath('data.charges.0.manual_price', true);
    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['description' => 'Fone de ouvido', 'unit_price' => '15.00'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.charges.1.product_id', null)
        ->assertJsonPath('data.amount_due', '20.00');
    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['description' => 'Sem preço'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_price']);
    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['unit_price' => '2.00'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

it('refuses products of another store, inactive products and closed sessions', function () {
    $attendant = Attendant::factory()->create();
    $headers = cyberCoffeAuth($attendant);
    $session = activeSessionFor($attendant);
    $closed = activeSessionFor($attendant, ['status' => 'encerrada', 'closed_at' => now()]);
    $product = Product::factory()->for($attendant->store)->create();

    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => Product::factory()->create()->id], $headers)->assertNotFound();
    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => Product::factory()->for($attendant->store)->inactive()->create()->id], $headers)->assertNotFound();
    $this->postJson(route('cybercoffe.sessions.items.store', StationSession::factory()->create()->id), ['product_id' => $product->id], $headers)->assertNotFound();
    $this->postJson(route('cybercoffe.sessions.items.store', $closed->id), ['product_id' => $product->id], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session']);
    $this->assertDatabaseCount('cc_session_charges', 0);
});

it('removes an unpaid item but never a time charge or an item already paid', function () {
    $attendant = Attendant::factory()->create();
    $headers = cyberCoffeAuth($attendant);
    $session = activeSessionFor($attendant, ['amount_due' => '0.00']);
    $soda = Product::factory()->for($attendant->store)->create(['price' => '5.00']);
    $timeCharge = $session->charges()->create(['store_id' => $session->store_id, 'kind' => 'tempo', 'description' => '1 hora', 'minutes' => 60, 'amount' => '0.00']);

    $firstId = $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => $soda->id], $headers)->json('data.charges.1.id');
    $secondId = $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => $soda->id], $headers)->json('data.charges.2.id');

    $this->deleteJson(route('cybercoffe.sessions.items.destroy', [$session->id, $secondId]), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.amount_due', '5.00');
    $this->deleteJson(route('cybercoffe.sessions.items.destroy', [$session->id, $timeCharge->id]), [], $headers)
        ->assertUnprocessable();

    Payment::factory()->create(['store_id' => $session->store_id, 'station_session_id' => $session->id, 'received_by' => $attendant->id, 'amount' => '5.00']);

    $this->deleteJson(route('cybercoffe.sessions.items.destroy', [$session->id, $firstId]), [], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['charge' => 'O item já foi pago e não pode ser removido.']);
    expect($session->fresh()->amount_due)->toBe('5.00');
});

it('charges printed pages at the price per page and counts them on the bill', function () {
    $attendant = Attendant::factory()->create();
    $headers = cyberCoffeAuth($attendant);
    $mono = Product::factory()->for($attendant->store)->printing('0.50')->create();
    $session = activeSessionFor($attendant);

    $this->postJson(route('cybercoffe.sessions.prints.store', $session->id), ['pages' => 4], $headers)
        ->assertCreated()
        ->assertJsonPath('data.amount_due', '8.00')
        ->assertJsonPath('data.bill.printed_pages', 4)
        ->assertJsonPath('data.bill.prints_amount', '2.00')
        ->assertJsonPath('data.charges.0.kind', 'impressao');

    $color = Product::factory()->for($attendant->store)->printing('2.00')->create();

    $this->postJson(route('cybercoffe.sessions.prints.store', $session->id), ['pages' => 1], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_id' => 'Informe o tipo de impressão.']);
    $this->postJson(route('cybercoffe.sessions.prints.store', $session->id), ['pages' => 1, 'product_id' => $color->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.bill.printed_pages', 5)
        ->assertJsonPath('data.bill.prints_amount', '4.00');
    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => $mono->id], $headers)
        ->assertNotFound();
});

it('asks for a print price before counting pages', function () {
    $attendant = Attendant::factory()->create();
    $session = activeSessionFor($attendant);

    $this->postJson(route('cybercoffe.sessions.prints.store', $session->id), ['pages' => 2], cyberCoffeAuth($attendant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_id' => 'Cadastre um preço de impressão antes de registrar páginas.']);
});

it('lets the station agent report printed pages once per spooler job', function () {
    $attendant = Attendant::factory()->create();
    $station = Station::factory()->for($attendant->store)->create(['status' => 'ocupada']);
    Product::factory()->for($attendant->store)->printing('0.50')->create();
    $session = activeSessionFor($attendant, ['station_id' => $station->id]);
    $headers = cyberCoffeAuth($station);

    $this->postJson(route('cybercoffe.agent.prints'), ['pages' => 3, 'job_id' => 'spool-17'], $headers)
        ->assertCreated()
        ->assertJsonPath('session.bill.printed_pages', 3)
        ->assertJsonPath('session.charges.0.added_by_agent', true);
    $this->postJson(route('cybercoffe.agent.prints'), ['pages' => 3, 'job_id' => 'spool-17'], $headers)
        ->assertCreated()
        ->assertJsonPath('session.bill.printed_pages', 3);

    expect($session->fresh()->amount_due)->toBe('7.50');
});

it('refuses agent prints when the station has no active session', function () {
    $station = Station::factory()->create();
    Product::factory()->for($station->store)->printing()->create();

    $this->postJson(route('cybercoffe.agent.prints'), ['pages' => 1], cyberCoffeAuth($station))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session']);
});

it('lists products and prints on the session ticket', function () {
    $attendant = Attendant::factory()->create();
    $headers = cyberCoffeAuth($attendant);
    $session = activeSessionFor($attendant, ['amount_due' => '0.00']);
    Product::factory()->for($attendant->store)->printing('0.50')->create();
    $water = Product::factory()->for($attendant->store)->create(['name' => 'Água', 'price' => '3.00']);
    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => $water->id, 'quantity' => 2], $headers);
    $this->postJson(route('cybercoffe.sessions.prints.store', $session->id), ['pages' => 4], $headers);

    $content = $this->postJson(route('cybercoffe.sessions.tickets.store', $session->id), [], $headers)->assertCreated()->json('data.content');

    expect($content)->toContain('2x Agua')
        ->toContain('R$ 6,00')
        ->toContain('Paginas impressas')
        ->toContain('R$ 8,00');
});

it('reports products sold and pages printed per station in the period', function () {
    $this->travelTo('2026-11-10 12:00:00');
    $manager = Attendant::factory()->manager()->create();
    $headers = cyberCoffeAuth($manager);
    $station = Station::factory()->for($manager->store)->create(['name' => 'PC-01']);
    $session = activeSessionFor($manager, ['station_id' => $station->id, 'amount_due' => '0.00']);
    Product::factory()->for($manager->store)->printing('0.50')->create();
    $water = Product::factory()->for($manager->store)->create(['name' => 'Água', 'price' => '3.00']);
    $this->postJson(route('cybercoffe.sessions.items.store', $session->id), ['product_id' => $water->id, 'quantity' => 3], $headers);
    $this->postJson(route('cybercoffe.sessions.prints.store', $session->id), ['pages' => 10], $headers);
    activeSessionFor(Attendant::factory()->create())->charges()->create([
        'store_id' => StationSession::latest('id')->first()->store_id, 'kind' => 'produto', 'description' => 'Outra loja', 'minutes' => 0, 'quantity' => 9, 'amount' => '90.00',
    ]);

    $this->getJson(route('cybercoffe.reports.consumption', ['from' => '2026-11-01', 'to' => '2026-11-30']), $headers)
        ->assertOk()
        ->assertJsonPath('products_amount', '9.00')
        ->assertJsonPath('products.0.description', 'Água')
        ->assertJsonPath('products.0.quantity', 3)
        ->assertJsonPath('printed_pages', 10)
        ->assertJsonPath('prints_amount', '5.00')
        ->assertJsonPath('prints_by_station.0.name', 'PC-01')
        ->assertJsonPath('prints_by_station.0.pages', 10);
    $this->getJson(route('cybercoffe.reports.consumption', ['from' => '2026-12-01', 'to' => '2026-12-31']), $headers)
        ->assertOk()
        ->assertJsonPath('printed_pages', 0)
        ->assertJsonCount(0, 'products');
});
