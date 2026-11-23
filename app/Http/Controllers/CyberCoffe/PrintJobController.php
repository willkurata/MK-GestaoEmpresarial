<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Actions\CyberCoffe\BuildPrintTicket;
use App\Http\Resources\CyberCoffe\PrintJobResource;
use App\Models\CyberCoffe\PrintJob;
use App\Models\CyberCoffe\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Print queue for thermal tickets. The panel creates jobs; a local print
 * bridge (agent or QZ Tray) polls pending jobs, sends the ESC/POS payload to
 * the printer and marks them as printed.
 */
class PrintJobController extends CyberCoffeController
{
    private const array METHOD_LABELS = ['pix' => 'Pix', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'creditos' => 'Créditos'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::in(['pendente', 'impresso'])]]);

        $jobs = $this->currentStore($request)->printJobs()
            ->where('status', $request->query('status', 'pendente'))
            ->oldest('id')
            ->limit(50)
            ->get();

        return PrintJobResource::collection($jobs);
    }

    public function storeForSession(Request $request, int $session, BuildPrintTicket $buildPrintTicket): JsonResponse
    {
        $store = $this->currentStore($request);
        $session = $store->stationSessions()->with(['station', 'customer', 'charges'])->findOrFail($session);
        $width = $this->validatedWidth($request);

        $amountDueInCents = $session->projectedTotalInCents();
        $amountPaidInCents = $session->amountPaidInCents();
        $rows = [
            ['Sessão', '#'.$session->id],
            ['Estação', $session->station->name],
            ['Cliente', $session->customer_id !== null ? $session->customer->name : 'Avulso'],
            ['Cobrança', $session->isPackage() ? 'Pacote' : 'Tempo livre'],
            ['Início', $session->started_at->format('d/m H:i')],
        ];

        if ($session->isActive() && $session->ends_at !== null) {
            $rows[] = ['Termina às', $session->ends_at->format('d/m H:i')];
        }

        if ($session->closed_at !== null) {
            $rows[] = ['Encerrada', $session->closed_at->format('d/m H:i')];
        }

        $rows[] = ['Tempo usado', intdiv($session->elapsedSeconds(), 60).' min'];

        foreach ($session->charges as $charge) {
            $label = $charge->isTime() ? $charge->description : $charge->quantity.'x '.$charge->description;
            $rows[] = [$label, $this->money($charge->amountInCents())];
        }

        if ($session->isActive() && ! $session->isPackage()) {
            $rows[] = ['Tempo livre até agora', $this->money($session->openAmountInCents())];
        }

        $printedPages = (int) $session->charges->where('kind', 'impressao')->sum('quantity');

        if ($printedPages > 0) {
            $rows[] = ['Páginas impressas', (string) $printedPages];
        }

        $rows[] = ['Total', $this->money($amountDueInCents)];
        $rows[] = ['Pago', $this->money($amountPaidInCents)];
        $rows[] = ['Em aberto', $this->money(max(0, $amountDueInCents - $amountPaidInCents))];

        $job = $this->queue($request, $store, $buildPrintTicket->handle($store->name, 'Comprovante de uso', $rows, $width), [
            'kind' => 'sessao',
            'width' => $width,
            'station_session_id' => $session->id,
        ]);

        return (new PrintJobResource($job))->response()->setStatusCode(201);
    }

    public function storeForPayment(Request $request, int $payment, BuildPrintTicket $buildPrintTicket): JsonResponse
    {
        $store = $this->currentStore($request);
        $payment = $store->payments()->with('customer')->findOrFail($payment);
        $width = $this->validatedWidth($request);

        $rows = [
            ['Pagamento', '#'.$payment->id],
            ['Referente a', $payment->purpose === 'creditos' ? 'Compra de créditos' : 'Sessão #'.$payment->station_session_id],
            ['Cliente', $payment->customer_id !== null ? $payment->customer->name : 'Avulso'],
            ['Forma', self::METHOD_LABELS[$payment->method]],
            ['Valor', $this->money($this->cents($payment->amount))],
        ];

        if ($payment->method === 'dinheiro') {
            $rows[] = ['Recebido', $this->money($this->cents($payment->amount_received))];
            $rows[] = ['Troco', $this->money($this->cents($payment->change_amount))];
        }

        if ($payment->purpose === 'creditos' && $payment->customer_id !== null) {
            $rows[] = ['Saldo atual', $this->money($payment->customer->creditBalanceInCents())];
        }

        if ($payment->reference) {
            $rows[] = ['Referência', $payment->reference];
        }

        $rows[] = ['Data', $payment->paid_at->format('d/m/Y H:i')];

        $job = $this->queue($request, $store, $buildPrintTicket->handle($store->name, 'Comprovante de pagamento', $rows, $width), [
            'kind' => 'pagamento',
            'width' => $width,
            'payment_id' => $payment->id,
        ]);

        return (new PrintJobResource($job))->response()->setStatusCode(201);
    }

    public function markPrinted(Request $request, int $printJob): PrintJobResource
    {
        $printJob = $this->currentStore($request)->printJobs()->findOrFail($printJob);

        if ($printJob->status !== 'impresso') {
            $printJob->update(['status' => 'impresso', 'printed_at' => now()]);
        }

        return new PrintJobResource($printJob);
    }

    private function validatedWidth(Request $request): int
    {
        $validated = $request->validate(['width' => ['nullable', 'integer', Rule::in([32, 48])]]);

        return (int) ($validated['width'] ?? 48);
    }

    /**
     * @param  array{content: string, payload: string}  $ticket
     * @param  array<string, mixed>  $attributes
     */
    private function queue(Request $request, Store $store, array $ticket, array $attributes): PrintJob
    {
        return $store->printJobs()->create([
            ...$attributes,
            ...$ticket,
            'requested_by' => $this->currentAttendant($request)->id,
            'status' => 'pendente',
        ]);
    }

    private function cents(float|string|null $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
