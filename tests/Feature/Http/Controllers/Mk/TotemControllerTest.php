<?php

use App\Models\PrintOrder;
use App\Models\Totem;
use App\Models\User;
use App\PrintOrderStatus;
use App\TotemStatus;
use Illuminate\Support\Facades\Storage;

it('redirects guests to the sign-in page', function () {
    $this->get(route('mk.totems.index'))->assertRedirect(route('login'));
});

it('registers a totem and returns its one-time pairing code', function () {
    $response = $this->actingAs(User::factory()->create())->postJson(route('mk.totems.store'), [
        'name' => 'Totem Biblioteca',
        'location' => 'Térreo',
        'supports_color' => true,
        'supports_duplex' => false,
        'price_bw_per_side' => '0.40',
        'price_color_per_side' => '1.20',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.totem.name', 'Totem Biblioteca')
        ->assertJsonPath('data.totem.paired', false)
        ->assertJsonMissingPath('data.totem.api_token_hash');
    $totem = Totem::query()->where('code', $response->json('data.totem.code'))->sole();
    expect($totem->pairingCodeMatches($response->json('data.pairing_code')))->toBeTrue();
});

it('requires the color price for a color totem', function () {
    $this->actingAs(User::factory()->create())->postJson(route('mk.totems.store'), [
        'name' => 'Totem',
        'supports_color' => true,
        'supports_duplex' => false,
        'price_bw_per_side' => '0.40',
    ])->assertUnprocessable()->assertJsonValidationErrors('price_color_per_side');
});

it('revokes a totem so its device token stops working', function () {
    $totem = Totem::factory()->create();
    $token = $totem->issueApiToken();

    $this->actingAs(User::factory()->create())->postJson(route('mk.totems.revoke', $totem))
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');

    expect($totem->fresh()->status)->toBe(TotemStatus::Revoked);
    $this->postJson(route('totem.agent.jobs.claim'), [], ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
});

it('lists totems with their queue counts', function () {
    Storage::fake('local');
    $totem = Totem::factory()->create();
    PrintOrder::factory()->for($totem)->queued()->count(2)->create();

    $this->actingAs(User::factory()->create())->getJson(route('mk.totems.index'))
        ->assertOk()
        ->assertJsonPath('data.0.code', $totem->code)
        ->assertJsonPath('data.0.queue', ['queued' => 2, 'printing' => 0, 'failed' => 0]);
});

it('requeues a paid failed order and refunds a queued one', function () {
    Storage::fake('local');
    $superAdmin = User::factory()->create();
    $totem = Totem::factory()->create();
    $failed = PrintOrder::factory()->for($totem)->queued()->create();
    $failed->transitionTo(PrintOrderStatus::Failed, 'totem', ['failure_reason' => 'media-jam']);
    $queued = PrintOrder::factory()->for($totem)->queued()->create();

    $this->actingAs($superAdmin)->postJson(route('mk.totems.orders.requeue', [$totem, $failed]))
        ->assertOk()
        ->assertJsonPath('data.status', 'queued');
    $this->actingAs($superAdmin)->postJson(route('mk.totems.orders.refund', [$totem, $queued]), ['reason' => 'Cliente desistiu'])
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    expect($failed->events()->latest('id')->value('details'))->toBe(['user_id' => $superAdmin->id]);
});

it('returns 404 for an order of another totem', function () {
    Storage::fake('local');
    $totem = Totem::factory()->create();
    $order = PrintOrder::factory()->queued()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('mk.totems.orders.refund', [$totem, $order]), ['reason' => 'x'])
        ->assertNotFound();
    expect($order->fresh()->status)->toBe(PrintOrderStatus::Queued);
});
