<?php

namespace App\Http\Controllers\Api\Gym;

use App\Actions\Gym\Payments\PaymentGateway;
use App\Actions\Gym\RecordInvoicePayment;
use App\Actions\Gym\RefundPayment;
use App\Http\Controllers\Controller;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use App\Models\Gym\GymWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives payment confirmations/refunds from the gateway. Signed with HMAC-SHA256 and idempotent per event id.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, RecordInvoicePayment $recordInvoicePayment, RefundPayment $refundPayment): JsonResponse
    {
        $secret = (string) config('academia.payments.webhook_secret');
        $signature = (string) $request->header('X-Academia-Signature');

        if ($secret === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Assinatura inválida.');
        }

        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['payment.confirmed', 'payment.refunded'])],
            'charge_reference' => ['required', 'string', 'max:255'],
            'payment_id' => ['required', 'string', 'max:255'],
            'method' => ['required', Rule::in(['pix', 'cartao', 'boleto'])],
            'amount' => ['required', 'numeric'],
            'occurred_at' => ['required', 'date'],
        ]);

        try {
            DB::transaction(fn () => $this->process($validated, $gateway, $recordInvoicePayment, $refundPayment));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicado']);
        }

        return response()->json(['status' => 'processado']);
    }

    /**
     * The event row is written in the same transaction, so a failed processing can be retried by the gateway.
     *
     * @param  array<string, mixed>  $validated
     */
    private function process(array $validated, PaymentGateway $gateway, RecordInvoicePayment $recordInvoicePayment, RefundPayment $refundPayment): void
    {
        $event = GymWebhookEvent::create([
            'provider' => $gateway->name(),
            'event_id' => $validated['event_id'],
            'type' => $validated['type'],
        ]);

        $invoice = GymInvoice::query()->where('gateway_reference', $validated['charge_reference'])->firstOrFail();

        if ($validated['type'] === 'payment.confirmed') {
            if ($invoice->status === GymInvoice::STATUS_PENDING) {
                $recordInvoicePayment->handle($invoice, [
                    'method' => $validated['method'],
                    'amount' => (string) $validated['amount'],
                    'source' => 'gateway',
                    'gateway_payment_id' => $validated['payment_id'],
                    'paid_at' => CarbonImmutable::parse($validated['occurred_at']),
                ]);
            }
        } else {
            $payment = GymPayment::query()
                ->where('gym_id', $invoice->gym_id)
                ->where('gateway_payment_id', $validated['payment_id'])
                ->where('status', GymPayment::STATUS_CONFIRMED)
                ->first();

            if ($payment !== null) {
                $refundPayment->handle($payment, 'Estorno informado pelo gateway', null);
            }
        }

        $event->forceFill(['processed_at' => now()])->save();
    }
}
