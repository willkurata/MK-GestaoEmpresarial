<?php

use App\Actions\Totem\QrSessionToken;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Illuminate\Support\Facades\Storage;

it('shows printer status, the public queue, prices and a working QR code', function () {
    Storage::fake('local');
    $totem = Totem::factory()->online()->create(['printer_reasons' => ['media-low'], 'paper_level' => 15]);
    $queued = PrintOrder::factory()->for($totem)->queued()->create();
    PrintOrder::factory()->for($totem)->priced()->create();
    PrintOrder::factory()->for($totem)->create(['status' => PrintOrderStatus::Ready, 'ready_at' => now()->subHour()]);

    $response = $this->getJson(route('totem.display', $totem));

    $response->assertOk()
        ->assertJsonPath('data.totem.code', $totem->code)
        ->assertJsonPath('data.printer.state', 'idle')
        ->assertJsonPath('data.printer.reasons', ['media-low'])
        ->assertJsonPath('data.printer.paper_level', 15)
        ->assertJsonPath('data.queue', [['pickup_code' => $queued->pickup_code, 'status' => 'queued', 'progress' => null]])
        ->assertJsonPath('data.pricing.bw_per_side', '0.50')
        ->assertJsonPath('data.pricing.color_per_side', '1.50')
        ->assertJsonPath('data.pricing.accepted_extensions', ['pdf', 'jpg', 'jpeg', 'png']);
    expect($response->json('data.session_qr.svg'))->toContain('<svg');
    parse_str((string) parse_url($response->json('data.session_qr.url'), PHP_URL_QUERY), $query);
    expect(app(QrSessionToken::class)->isValid($totem, $query['t']))->toBeTrue();
});

it('reports the printer offline when the agent stops sending heartbeats', function () {
    $totem = Totem::factory()->create(['last_seen_at' => now()->subMinutes(5), 'printer_state' => 'idle']);

    $this->getJson(route('totem.display', $totem))->assertOk()->assertJsonPath('data.printer.state', 'offline');
});

it('does not expose customer files or tokens', function () {
    Storage::fake('local');
    $totem = Totem::factory()->online()->create();
    $order = PrintOrder::factory()->for($totem)->queued()->create();

    $content = $this->getJson(route('totem.display', $totem))->assertOk()->getContent();

    expect($content)->not->toContain($order->file_name)
        ->not->toContain($order->public_id)
        ->not->toContain($order->order_token_hash);
});

it('returns 404 for a revoked totem', function () {
    $this->getJson(route('totem.display', Totem::factory()->revoked()->create()))->assertNotFound();
});
