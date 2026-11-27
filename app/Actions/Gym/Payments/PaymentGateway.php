<?php

namespace App\Actions\Gym\Payments;

use App\Models\Gym\GymInvoice;

/**
 * Boundary to the payment provider (Pix Automático, cartão tokenizado, boleto).
 * The concrete provider (Asaas, Efí...) is still a commercial decision; see WIL-43.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Registers the charge for an invoice at the provider.
     *
     * @return array{reference: string, instructions: array<string, string>}
     */
    public function createCharge(GymInvoice $invoice): array;
}
