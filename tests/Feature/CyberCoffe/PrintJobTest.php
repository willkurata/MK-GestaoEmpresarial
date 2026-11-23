<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Payment;
use App\Models\CyberCoffe\PrintJob;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;

it('queues an ESC/POS session ticket for the print bridge', function () {
    $attendant = Attendant::factory()->create();
    $attendant->store->update(['name' => 'Cyber Café Ação']);
    $station = Station::factory()->for($attendant->store)->create(['name' => 'PC-07']);
    $session = StationSession::factory()->create([
        'store_id' => $attendant->store_id,
        'station_id' => $station->id,
        'opened_by' => $attendant->id,
        'amount_due' => '6.00',
    ]);
    $headers = cyberCoffeAuth($attendant);

    $response = $this->postJson(route('cybercoffe.sessions.tickets.store', $session->id), ['width' => 32], $headers);

    $response->assertCreated()->assertJsonPath('data.status', 'pendente')->assertJsonPath('data.width', 32);
    $bytes = base64_decode($response->json('data.payload_base64'));
    expect($bytes)->toStartWith("\x1B\x40")
        ->toEndWith("\x1D\x56\x41\x03")
        ->toContain('CYBER CAFE ACAO')
        ->toContain('PC-07')
        ->and($response->json('data.content'))->toContain('R$ 6,00');

    foreach (explode("\n", trim($response->json('data.content'))) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(32);
    }

    $this->getJson(route('cybercoffe.print-jobs.index'), $headers)->assertOk()->assertJsonCount(1, 'data');
});

it('queues a payment ticket with cash change', function () {
    $attendant = Attendant::factory()->create();
    $payment = Payment::factory()->create([
        'store_id' => $attendant->store_id,
        'received_by' => $attendant->id,
        'method' => 'dinheiro',
        'amount' => '6.00',
        'amount_received' => '10.00',
        'change_amount' => '4.00',
    ]);

    $this->postJson(route('cybercoffe.payments.tickets.store', $payment->id), [], cyberCoffeAuth($attendant))
        ->assertCreated()
        ->assertJsonPath('data.kind', 'pagamento')
        ->assertJsonPath('data.width', 48)
        ->assertJson(fn ($json) => $json->where('data.content', fn (string $content) => str_contains($content, 'Troco') && str_contains($content, 'R$ 4,00'))->etc());
});

it('marks a print job as printed and removes it from the pending queue', function () {
    $attendant = Attendant::factory()->create();
    $session = StationSession::factory()->create(['store_id' => $attendant->store_id, 'opened_by' => $attendant->id]);
    $headers = cyberCoffeAuth($attendant);
    $jobId = $this->postJson(route('cybercoffe.sessions.tickets.store', $session->id), [], $headers)->json('data.id');

    $this->postJson(route('cybercoffe.print-jobs.printed', $jobId), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'impresso');

    $this->getJson(route('cybercoffe.print-jobs.index'), $headers)->assertOk()->assertJsonCount(0, 'data');
});

it('does not expose or print jobs of another store', function () {
    $attendant = Attendant::factory()->create();
    $otherSession = StationSession::factory()->create();
    $otherAttendant = Attendant::factory()->for($otherSession->store)->create();
    $otherJob = PrintJob::create([
        'store_id' => $otherSession->store_id,
        'requested_by' => $otherAttendant->id,
        'kind' => 'sessao',
        'width' => 48,
        'content' => 'x',
        'payload' => 'eA==',
    ]);
    $headers = cyberCoffeAuth($attendant);

    $this->getJson(route('cybercoffe.print-jobs.index'), $headers)->assertOk()->assertJsonCount(0, 'data');
    $this->postJson(route('cybercoffe.print-jobs.printed', $otherJob->id), [], $headers)->assertNotFound();
    $this->postJson(route('cybercoffe.sessions.tickets.store', $otherSession->id), [], $headers)->assertNotFound();
    expect($otherJob->fresh()->status)->toBe('pendente');
});
