<?php

use App\Actions\Totem\PrintOrderPayments;
use App\Actions\Totem\PrintQueue;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Illuminate\Support\Facades\Storage;

it('expires unpaid orders past their deadline and deletes their files', function () {
    Storage::fake('local');
    $this->freezeTime();
    $order = PrintOrder::factory()->for(Totem::factory()->online())->priced()->create();
    app(PrintOrderPayments::class)->checkout($order);
    $draft = PrintOrder::factory()->create(['expires_at' => now()->addMinutes(5)]);

    $this->travel(16)->minutes();
    $this->artisan('totem:maintain')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(PrintOrderStatus::Expired)
        ->and($order->payment->status)->toBe('expired')
        ->and($order->file_path)->toBeNull()
        ->and($draft->fresh()->status)->toBe(PrintOrderStatus::Expired);
});

it('puts a job back in the queue when the agent lease expires', function () {
    Storage::fake('local');
    $this->freezeTime();
    $totem = Totem::factory()->create();
    $order = PrintOrder::factory()->for($totem)->queued()->create();
    app(PrintQueue::class)->claimNext($totem);

    $this->travel(6)->minutes();
    $this->artisan('totem:maintain')->assertSuccessful();

    expect($order->fresh()->status)->toBe(PrintOrderStatus::Queued)
        ->and($order->events()->latest('id')->value('actor'))->toBe('system');
});

it('keeps a job whose lease is still valid', function () {
    Storage::fake('local');
    $this->freezeTime();
    $totem = Totem::factory()->create();
    $order = PrintOrder::factory()->for($totem)->queued()->create();
    app(PrintQueue::class)->claimNext($totem);

    $this->travel(4)->minutes();
    $this->artisan('totem:maintain')->assertSuccessful();

    expect($order->fresh()->status)->toBe(PrintOrderStatus::Printing);
});

it('deletes files past retention and refunds a paid order that was never printed', function () {
    Storage::fake('local');
    $this->freezeTime();
    $order = PrintOrder::factory()->queued()->create();
    $filePath = $order->file_path;

    $this->travel(25)->hours();
    $this->artisan('totem:maintain')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(PrintOrderStatus::Refunded)
        ->and($order->payment->status)->toBe('refunded')
        ->and($order->file_path)->toBeNull();
    Storage::disk('local')->assertMissing($filePath);
});

it('is scheduled every minute', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('totem:maintain')->assertSuccessful();
});
