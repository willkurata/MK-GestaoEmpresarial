<?php

namespace App\Jobs;

use App\Actions\Totem\PdfPageCounter;
use App\Actions\Totem\PrintQuote;
use App\Models\PrintOrder;
use App\PrintOrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Counts the pages of an uploaded file and prices the order.
 *
 * Runs on the queue so large uploads do not block the request. Conversion of
 * office documents (Gotenberg) and antivirus scanning (ClamAV) belong here
 * once those services are part of the stack.
 */
class AnalyzePrintOrderFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public PrintOrder $order, public string $filePath) {}

    public function handle(PdfPageCounter $pdfPageCounter): void
    {
        $order = $this->order->fresh();

        if ($order === null || $order->status !== PrintOrderStatus::Draft || $order->file_path !== $this->filePath) {
            return;
        }

        $pageCount = $order->file_mime === 'application/pdf'
            ? $pdfPageCounter->count((string) Storage::disk(config('totem.disk'))->get($order->file_path))
            : 1;

        if ($pageCount === null || $pageCount > (int) config('totem.max_pages')) {
            $reason = $pageCount === null
                ? 'Não foi possível ler o PDF (protegido por senha ou corrompido).'
                : 'O arquivo passa do limite de '.config('totem.max_pages').' páginas.';

            $order->transitionTo(PrintOrderStatus::Failed, 'system', ['failure_reason' => $reason, 'file_analyzed_at' => now()]);
            $order->purgeFile();

            return;
        }

        $order->forceFill(['page_count' => $pageCount, 'file_analyzed_at' => now()]);

        try {
            $quote = PrintQuote::for($order);
        } catch (InvalidArgumentException) {
            $order->page_range = null;
            $quote = PrintQuote::for($order);
        }

        $order->forceFill($quote)->save();
    }
}
