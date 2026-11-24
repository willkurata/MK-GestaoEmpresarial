<?php

namespace App\Actions\Totem\Pix;

use App\Models\PrintOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Contract for the Pix payment service provider (PSP).
 *
 * The PSP is not chosen yet (Mercado Pago, Efí, Asaas, PagBank...). Each
 * real driver must create dynamic BR Code charges with an idempotency key,
 * validate webhook signatures and confirm payments by querying the PSP:
 * the totem never decides on its own that an order was paid.
 */
interface PixGateway
{
    public function name(): string;

    public function createCharge(PrintOrder $order, string $amount, CarbonImmutable $expiresAt): PixCharge;

    /**
     * Return the charge external id when the webhook is authentic and reports a payment.
     */
    public function paidChargeFromWebhook(Request $request): ?string;

    /**
     * Ask the PSP whether the charge is really paid (never trust the webhook body alone).
     */
    public function confirmPaid(string $externalId): bool;

    public function refund(string $externalId, string $amount): void;
}
