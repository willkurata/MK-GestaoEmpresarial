<?php

use App\Actions\Totem\QrSessionToken;
use App\Jobs\AnalyzePrintOrderFile;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Database\Factories\PrintOrderFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function orderHeaders(string $orderToken = PrintOrderFactory::ORDER_TOKEN): array
{
    return ['X-Order-Token' => $orderToken, 'Accept' => 'application/json'];
}

describe('session from the QR code', function () {
    it('opens a draft order with its own private token', function () {
        $totem = Totem::factory()->online()->create();
        $qrToken = app(QrSessionToken::class)->issue($totem)['token'];

        $response = $this->postJson(route('totem.sessions.store', $totem), ['token' => $qrToken]);

        $response->assertCreated()->assertJsonStructure(['data' => ['order_id', 'order_token']]);
        $order = PrintOrder::query()->where('public_id', $response->json('data.order_id'))->sole();
        expect($order->status)->toBe(PrintOrderStatus::Draft)
            ->and($order->totem_id)->toBe($totem->id)
            ->and($order->pickup_code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ2-9]{3}$/')
            ->and($order->tokenMatches($response->json('data.order_token')))->toBeTrue();
    });

    it('returns 410 when the QR token has rotated out', function () {
        $this->freezeTime();
        $totem = Totem::factory()->online()->create();
        $qrToken = app(QrSessionToken::class)->issue($totem)['token'];

        $this->travel(2)->minutes();

        $this->postJson(route('totem.sessions.store', $totem), ['token' => $qrToken])->assertStatus(410);
        expect(PrintOrder::query()->count())->toBe(0);
    });

    it('returns 410 for a QR token issued by another totem', function () {
        $totem = Totem::factory()->online()->create();
        $otherTotemToken = app(QrSessionToken::class)->issue(Totem::factory()->online()->create())['token'];

        $this->postJson(route('totem.sessions.store', $totem), ['token' => $otherTotemToken])->assertStatus(410);
    });

    it('returns 404 for a revoked totem', function () {
        $totem = Totem::factory()->revoked()->create();
        $qrToken = app(QrSessionToken::class)->issue($totem)['token'];

        $this->postJson(route('totem.sessions.store', $totem), ['token' => $qrToken])->assertNotFound();
    });
});

describe('order access', function () {
    it('returns 404 without the order token or with another order token', function (?string $orderToken) {
        $order = PrintOrder::factory()->create();

        $this->getJson(route('totem.orders.show', $order), array_filter(['X-Order-Token' => $orderToken]))
            ->assertNotFound();
    })->with([null, 'wrong-token']);

    it('shows the order in the contract shape', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->priced()->create();

        $this->getJson(route('totem.orders.show', $order), orderHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $order->public_id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.totem_code', $order->totem->code)
            ->assertJsonPath('data.file.page_count', 2)
            ->assertJsonPath('data.quote.total', '1.00')
            ->assertJsonMissingPath('data.file.path')
            ->assertJsonPath('data.payment', null);
    });
});

describe('file upload', function () {
    it('stores the pdf, counts its pages and prices the order', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->create();
        $file = UploadedFile::fake()->createWithContent('contrato.pdf', totemPdf(3));

        $response = $this->post(route('totem.orders.file', $order), ['file' => $file], orderHeaders());

        $response->assertOk()
            ->assertJsonPath('data.file.name', 'contrato.pdf')
            ->assertJsonPath('data.file.mime', 'application/pdf')
            ->assertJsonPath('data.file.page_count', 3)
            ->assertJsonPath('data.file.converting', false)
            ->assertJsonPath('data.quote.sides', 3)
            ->assertJsonPath('data.quote.total', '1.50');
        Storage::disk('local')->assertExists($order->fresh()->file_path);
    });

    it('dispatches the analysis job for the stored file', function () {
        Storage::fake('local');
        Queue::fake([AnalyzePrintOrderFile::class]);
        $order = PrintOrder::factory()->create();

        $this->post(route('totem.orders.file', $order), ['file' => UploadedFile::fake()->createWithContent('a.pdf', totemPdf(1))], orderHeaders())
            ->assertOk()
            ->assertJsonPath('data.file.converting', true)
            ->assertJsonPath('data.quote', null);

        Queue::assertPushed(AnalyzePrintOrderFile::class, fn (AnalyzePrintOrderFile $job) => $job->order->is($order) && $job->filePath === $order->fresh()->file_path);
    });

    it('rejects a file whose content is not an accepted type even with a pdf extension', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->create();
        // A real temporary file: fake uploads report the MIME type from the extension, not the content.
        $path = tempnam(sys_get_temp_dir(), 'totem');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00".str_repeat("\x00", 64).'This program cannot be run in DOS mode.');
        $file = new UploadedFile($path, 'virus.pdf', null, null, true);

        $this->post(route('totem.orders.file', $order), ['file' => $file], orderHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('rejects files larger than the configured limit', function () {
        Storage::fake('local');
        config(['totem.max_file_mb' => 1]);
        $order = PrintOrder::factory()->create();
        $file = UploadedFile::fake()->createWithContent('big.pdf', totemPdf(1))->size(2048);

        $this->post(route('totem.orders.file', $order), ['file' => $file], orderHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    });

    it('fails the order and deletes the file when the pdf cannot be read', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->create();
        $file = UploadedFile::fake()->createWithContent('protegido.pdf', totemPdf(2).'/Encrypt');

        $this->post(route('totem.orders.file', $order), ['file' => $file], orderHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Não foi possível ler o PDF (protegido por senha ou corrompido).');
        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('replaces the previous file and deletes it', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->priced()->create();
        $previousPath = $order->file_path;

        $this->post(route('totem.orders.file', $order), ['file' => UploadedFile::fake()->createWithContent('novo.pdf', totemPdf(5))], orderHeaders())
            ->assertOk()
            ->assertJsonPath('data.file.page_count', 5);

        Storage::disk('local')->assertMissing($previousPath);
    });

    it('returns 409 when the order is no longer a draft', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->queued()->create();

        $this->post(route('totem.orders.file', $order), ['file' => UploadedFile::fake()->createWithContent('a.pdf', totemPdf(1))], orderHeaders())
            ->assertConflict();
    });
});

describe('print options', function () {
    it('recalculates the quote with the server formula', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->priced()->create(['page_count' => 9]);

        // pages 1-5 → 5 pages; 2 per sheet → 3 sides; duplex → 2 sheets; 3 sides × 2 copies × R$ 1,50
        $this->patchJson(route('totem.orders.update', $order), ['options' => [
            'color' => 'color',
            'duplex' => true,
            'pages_per_sheet' => 2,
            'page_range' => '1-5',
            'copies' => 2,
        ]], orderHeaders())
            ->assertOk()
            ->assertJsonPath('data.quote', [
                'pages' => 5,
                'sides' => 3,
                'sheets' => 2,
                'copies' => 2,
                'per_side' => '1.50',
                'total' => '9.00',
            ]);
    });

    it('rejects a page range outside the file', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->priced()->create();

        $this->patchJson(route('totem.orders.update', $order), ['options' => ['page_range' => '1-3']], orderHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options.page_range' => 'O arquivo tem 2 página(s).']);
    });

    it('rejects color and duplex on a totem without those capabilities', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create(['supports_color' => false, 'supports_duplex' => false]);
        $order = PrintOrder::factory()->for($totem)->priced()->create();

        $this->patchJson(route('totem.orders.update', $order), ['options' => ['color' => 'color', 'duplex' => true]], orderHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options.color', 'options.duplex']);
    });

    it('rejects more copies than the totem allows', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create(['max_copies' => 5]);
        $order = PrintOrder::factory()->for($totem)->priced()->create();

        $this->patchJson(route('totem.orders.update', $order), ['options' => ['copies' => 6]], orderHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('options.copies');
    });
});

describe('checkout', function () {
    it('creates a single pix charge for the quoted amount', function () {
        Storage::fake('local');
        $totem = Totem::factory()->online()->create();
        $order = PrintOrder::factory()->for($totem)->priced()->create();

        $firstResponse = $this->postJson(route('totem.orders.checkout', $order), [], orderHeaders());
        $secondResponse = $this->postJson(route('totem.orders.checkout', $order), [], orderHeaders());

        $firstResponse->assertOk()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.payment.amount', '1.00')
            ->assertJsonPath('data.payment.status', 'pending');
        expect($firstResponse->json('data.payment.qr_svg'))->toContain('<svg');
        $secondResponse->assertOk()->assertJsonPath('data.payment.copy_paste', $firstResponse->json('data.payment.copy_paste'));
        expect($order->payment()->count())->toBe(1);
    });

    it('returns 409 while the totem is offline', function () {
        Storage::fake('local');
        $totem = Totem::factory()->create(['last_seen_at' => now()->subMinutes(10)]);
        $order = PrintOrder::factory()->for($totem)->priced()->create();

        $this->postJson(route('totem.orders.checkout', $order), [], orderHeaders())->assertConflict();
        expect($order->fresh()->status)->toBe(PrintOrderStatus::Draft);
    });

    it('requires an analyzed file before payment', function () {
        $order = PrintOrder::factory()->for(Totem::factory()->online())->create();

        $this->postJson(route('totem.orders.checkout', $order), [], orderHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Envie um arquivo válido antes de pagar.']);
    });

    it('locks the options once the charge exists', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->for(Totem::factory()->online())->priced()->create();
        $this->postJson(route('totem.orders.checkout', $order), [], orderHeaders())->assertOk();

        $this->patchJson(route('totem.orders.update', $order), ['options' => ['copies' => 10]], orderHeaders())
            ->assertConflict();
        expect($order->fresh()->copies)->toBe(1);
    });
});

describe('cancel', function () {
    it('cancels an unpaid order and deletes its file', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->for(Totem::factory()->online())->priced()->create();
        $this->postJson(route('totem.orders.checkout', $order), [], orderHeaders());

        $this->postJson(route('totem.orders.cancel', $order), [], orderHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.payment.status', 'canceled');
        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('returns 409 for a paid order', function () {
        Storage::fake('local');
        $order = PrintOrder::factory()->queued()->create();

        $this->postJson(route('totem.orders.cancel', $order), [], orderHeaders())->assertConflict();
        expect($order->fresh()->status)->toBe(PrintOrderStatus::Queued);
    });
});
