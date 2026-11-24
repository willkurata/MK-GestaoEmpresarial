<?php

namespace App\Actions\Totem;

use App\Actions\Totem\Pix\PixGateway;
use App\Exceptions\InvalidPrintOrderTransition;
use App\Models\PrintOrder;
use App\Models\PrintPayment;
use App\PrintOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrintOrderPayments
{
    public function __construct(private PixGateway $gateway) {}

    /**
     * Create the Pix charge for a priced draft. Calling it again returns the same charge.
     */
    public function checkout(PrintOrder $order): PrintOrder
    {
        return DB::transaction(function () use ($order): PrintOrder {
            $order = PrintOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === PrintOrderStatus::AwaitingPayment) {
                return $order;
            }

            if ($order->status !== PrintOrderStatus::Draft) {
                throw new InvalidPrintOrderTransition($order->status, PrintOrderStatus::AwaitingPayment);
            }

            if ($order->page_count === null || $order->quote_total === null || PrintQuote::toCents($order->quote_total) <= 0) {
                throw ValidationException::withMessages(['file' => 'Envie um arquivo válido antes de pagar.']);
            }

            $expiresAt = now()->addMinutes((int) config('totem.payment_ttl_minutes'));
            $charge = $this->gateway->createCharge($order, $order->quote_total, $expiresAt);

            $order->payment()->create([
                'provider' => $this->gateway->name(),
                'external_id' => $charge->externalId,
                'amount' => $order->quote_total,
                'status' => 'pending',
                'copy_paste' => $charge->copyPaste,
                'expires_at' => $expiresAt,
            ]);

            $order->transitionTo(PrintOrderStatus::AwaitingPayment, 'customer', ['expires_at' => $expiresAt]);

            return $order;
        });
    }

    /**
     * Apply a confirmed payment. Idempotent: duplicated webhooks are ignored.
     * A payment that arrives after the order expired or was canceled is refunded.
     */
    public function confirm(string $externalId): ?PrintOrder
    {
        $payment = PrintPayment::query()->where('external_id', $externalId)->first();

        if ($payment === null || ! $this->gateway->confirmPaid($externalId)) {
            return null;
        }

        $order = DB::transaction(function () use ($payment): PrintOrder {
            $payment = PrintPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = PrintOrder::query()->lockForUpdate()->findOrFail($payment->print_order_id);

            if ($payment->status !== 'pending') {
                return $order;
            }

            $payment->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            if ($order->status === PrintOrderStatus::AwaitingPayment) {
                $order->transitionTo(PrintOrderStatus::Queued, 'payment', [
                    'expires_at' => null,
                    'file_purge_at' => now()->addHours((int) config('totem.file_retention_hours')),
                ], ['external_id' => $payment->external_id]);
            }

            return $order;
        });

        if (in_array($order->status, [PrintOrderStatus::Expired, PrintOrderStatus::Canceled], true)) {
            $this->refund($order, 'payment', 'Pagamento recebido depois do pedido ser encerrado.');
        }

        return $order->refresh();
    }

    /**
     * Give the money back for a paid order that will not be printed.
     * When the PSP call fails the order stays as it was and the error is audited for a retry.
     */
    public function refund(PrintOrder $order, string $actor, string $reason): bool
    {
        $payment = $order->payment;

        if ($payment === null || $payment->status !== 'paid') {
            return false;
        }

        try {
            $this->gateway->refund($payment->external_id, $payment->amount);
        } catch (Throwable $exception) {
            report($exception);
            $order->recordEvent($order->status, $order->status, $actor, ['refund_error' => $exception->getMessage()]);

            return false;
        }

        DB::transaction(function () use ($order, $payment, $actor, $reason): void {
            $payment->forceFill(['status' => 'refunded', 'refunded_at' => now()])->save();
            $order->transitionTo(PrintOrderStatus::Refunded, $actor, ['failure_reason' => $order->failure_reason ?? $reason], ['reason' => $reason]);
        });

        $order->purgeFile();

        return true;
    }
}
