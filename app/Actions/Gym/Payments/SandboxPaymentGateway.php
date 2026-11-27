<?php

namespace App\Actions\Gym\Payments;

use App\Models\Gym\GymInvoice;
use Illuminate\Support\Str;

/**
 * Local gateway for development and tests. It returns clearly marked sandbox instructions and never moves money;
 * confirmations arrive through the signed webhook, exactly as a real provider would send them.
 */
class SandboxPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function createCharge(GymInvoice $invoice): array
    {
        $reference = 'sandbox_'.Str::uuid();

        $instructions = match ($invoice->payment_method) {
            'pix' => ['type' => 'pix', 'pix_copy_paste' => 'SANDBOX-PIX-'.$reference],
            'boleto' => ['type' => 'boleto', 'digitable_line' => 'SANDBOX-BOLETO-'.$reference],
            default => ['type' => 'cartao', 'checkout_reference' => $reference],
        };

        return [
            'reference' => $reference,
            'instructions' => ['environment' => 'sandbox', ...$instructions],
        ];
    }
}
