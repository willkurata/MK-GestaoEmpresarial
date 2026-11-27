<?php

namespace App\Actions\Gym;

use App\Actions\Gym\Payments\PaymentGateway;
use App\Models\Gym\GymInvoice;
use Illuminate\Validation\ValidationException;

/**
 * (Re)issues the boleto, Pix or card charge of a pending invoice at the payment provider,
 * e.g. when the student wants to pay by Pix an invoice that was issued as boleto.
 */
class GenerateInvoiceCharge
{
    public function __construct(private PaymentGateway $gateway) {}

    public function handle(GymInvoice $invoice, string $method): GymInvoice
    {
        if ($invoice->status !== GymInvoice::STATUS_PENDING) {
            throw ValidationException::withMessages(['invoice' => 'Somente mensalidades pendentes podem gerar cobrança.']);
        }

        $invoice->payment_method = $method;
        $charge = $this->gateway->createCharge($invoice);
        $invoice->forceFill([
            'gateway_reference' => $charge['reference'],
            'payment_instructions' => $charge['instructions'],
        ])->save();

        return $invoice;
    }
}
