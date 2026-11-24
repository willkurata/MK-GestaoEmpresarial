<?php

use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

function agentHeaders(string $token): array
{
    return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
}

describe('pairing', function () {
    it('exchanges a valid pairing code for a device token only once', function () {
        $totem = Totem::factory()->create();
        $pairingCode = $totem->issuePairingCode();

        $response = $this->postJson(route('totem.agent.pair'), ['totem_code' => $totem->code, 'pairing_code' => $pairingCode]);

        $response->assertCreated()->assertJsonPath('data.totem.code', $totem->code);
        expect(Totem::findActiveByApiToken($response->json('data.token'))?->is($totem))->toBeTrue();
        $this->postJson(route('totem.agent.pair'), ['totem_code' => $totem->code, 'pairing_code' => $pairingCode])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pairing_code' => 'Código de pareamento inválido ou expirado.']);
    });

    it('rejects an expired pairing code', function () {
        $this->freezeTime();
        $totem = Totem::factory()->create();
        $pairingCode = $totem->issuePairingCode();
        $this->travel(31)->minutes();

        $this->postJson(route('totem.agent.pair'), ['totem_code' => $totem->code, 'pairing_code' => $pairingCode])
            ->assertUnprocessable();
        expect($totem->fresh()->api_token_hash)->toBeNull();
    });
});

describe('device authentication', function () {
    it('returns 401 without a valid token', function (?string $token) {
        $this->postJson(route('totem.agent.jobs.claim'), [], $token === null ? [] : agentHeaders($token))->assertUnauthorized();
    })->with([null, 'not-a-token']);

    it('returns 401 for the token of a revoked totem', function () {
        $totem = Totem::factory()->create();
        $token = $totem->issueApiToken();
        $totem->update(['status' => 'revoked']);

        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token))->assertUnauthorized();
    });

    it('invalidates the old token when it is rotated', function () {
        $totem = Totem::factory()->create();
        $oldToken = $totem->issueApiToken();

        $newToken = $this->postJson(route('totem.agent.token.rotate'), [], agentHeaders($oldToken))->assertOk()->json('data.token');
        Auth::forgetGuards();

        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($oldToken))->assertUnauthorized();
        Auth::forgetGuards();
        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($newToken))->assertNoContent();
    });
});

it('records the heartbeat and printer status', function () {
    $this->freezeTime();
    $totem = Totem::factory()->create();
    $token = $totem->issueApiToken();

    $this->postJson(route('totem.agent.heartbeat'), [
        'agent_version' => '0.1.0',
        'printer' => [
            'state' => 'stopped',
            'reasons' => ['media-empty'],
            'paper_level' => 0,
            'markers' => [['color' => 'black', 'level' => 40]],
        ],
    ], agentHeaders($token))->assertOk()->assertJsonPath('data.queued', 0);

    $totem->refresh();
    expect($totem->last_seen_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($totem->printer_state)->toBe('stopped')
        ->and($totem->printer_reasons)->toBe(['media-empty'])
        ->and($totem->printer_markers)->toBe([['color' => 'black', 'level' => 40]])
        ->and($totem->agent_version)->toBe('0.1.0');
});

describe('print jobs', function () {
    it('claims the oldest paid job of its own totem and keeps returning it until it is finished', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create();
        $token = $totem->issueApiToken();
        PrintOrder::factory()->queued()->create();
        PrintOrder::factory()->for($totem)->priced()->create();
        $oldest = PrintOrder::factory()->for($totem)->queued()->create();
        PrintOrder::factory()->for($totem)->queued()->create();

        $first = $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token));
        $second = $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token));

        $first->assertOk()
            ->assertJsonPath('data.id', $oldest->public_id)
            ->assertJsonPath('data.attempt', 1)
            ->assertJsonPath('data.options.pages', [1, 2])
            ->assertJsonPath('data.sheets_total', 2);
        $second->assertJsonPath('data.id', $oldest->public_id)->assertJsonPath('data.attempt', 1);
        expect($oldest->fresh()->status)->toBe(PrintOrderStatus::Printing);
    });

    it('returns 204 when nothing is paid', function () {
        $totem = Totem::factory()->create();

        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($totem->issueApiToken()))->assertNoContent();
    });

    it('downloads the file of a job it holds', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create();
        $token = $totem->issueApiToken();
        $order = PrintOrder::factory()->for($totem)->queued()->create();
        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token));

        $response = $this->get(route('totem.agent.jobs.file', $order), agentHeaders($token));

        $response->assertOk()->assertDownload('documento.pdf');
        expect($response->streamedContent())->toBe('%PDF-1.4 fake');
    });

    it('returns 404 for the job of another totem', function () {
        Storage::fake('local');
        $token = Totem::factory()->create()->issueApiToken();
        $otherTotem = Totem::factory()->create();
        $order = PrintOrder::factory()->for($otherTotem)->queued()->create();
        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($otherTotem->issueApiToken()));
        Auth::forgetGuards();

        $this->get(route('totem.agent.jobs.file', $order), agentHeaders($token))->assertNotFound();
        $this->postJson(route('totem.agent.jobs.complete', $order), [], agentHeaders($token))->assertNotFound();
        expect($order->fresh()->status)->toBe(PrintOrderStatus::Printing);
    });

    it('returns 409 when downloading a job that is not being printed', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create();
        $order = PrintOrder::factory()->for($totem)->queued()->create();

        $this->get(route('totem.agent.jobs.file', $order), agentHeaders($totem->issueApiToken()))->assertConflict();
    });

    it('marks the job ready, deletes the file and accepts a repeated completion', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create();
        $token = $totem->issueApiToken();
        $order = PrintOrder::factory()->for($totem)->queued()->create();
        $filePath = $order->file_path;
        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token));

        $this->postJson(route('totem.agent.jobs.complete', $order), ['sheets_printed' => 2], agentHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');
        $this->postJson(route('totem.agent.jobs.complete', $order), ['sheets_printed' => 2], agentHeaders($token))
            ->assertOk();

        $order->refresh();
        expect($order->status)->toBe(PrintOrderStatus::Ready)
            ->and($order->sheets_done)->toBe(2)
            ->and($order->file_path)->toBeNull()
            ->and($order->file_purged_at)->not->toBeNull()
            ->and($order->events()->where('to_status', 'ready')->count())->toBe(1);
        Storage::disk('local')->assertMissing($filePath);
    });

    it('requeues a retryable failure while attempts remain', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create();
        $token = $totem->issueApiToken();
        $order = PrintOrder::factory()->for($totem)->queued()->create();
        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token));

        $this->postJson(route('totem.agent.jobs.fail', $order), ['reason' => 'media-jam', 'retryable' => true], agentHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');
    });

    it('fails and refunds after the last attempt', function () {
        Storage::fake('local');
        config(['totem.max_print_attempts' => 1]);
        $totem = Totem::factory()->create();
        $token = $totem->issueApiToken();
        $order = PrintOrder::factory()->for($totem)->queued()->create();
        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token));

        $this->postJson(route('totem.agent.jobs.fail', $order), ['reason' => 'media-jam', 'retryable' => true], agentHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');

        $order->refresh();
        expect($order->failure_reason)->toBe('media-jam')
            ->and($order->payment->status)->toBe('refunded')
            ->and($order->file_path)->toBeNull();
    });

    it('caps reported progress at the total sheets', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create();
        $token = $totem->issueApiToken();
        $order = PrintOrder::factory()->for($totem)->queued()->create();
        $this->postJson(route('totem.agent.jobs.claim'), [], agentHeaders($token));

        $this->postJson(route('totem.agent.jobs.progress', $order), ['sheets_done' => 99], agentHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.sheets_done', 2);
    });
});
