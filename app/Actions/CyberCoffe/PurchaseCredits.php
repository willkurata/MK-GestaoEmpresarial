<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseCredits
{
    /**
     * Sell prepaid credits: records the payment and a positive ledger entry.
     *
     * @param  array{method: string, amount: string, amount_received?: string|null, reference?: string|null}  $data
     */
    public function handle(Customer $customer, Attendant $attendant, array $data): Payment
    {
        $amountInCents = (int) round(((float) $data['amount']) * 100);
        $amount = number_format($amountInCents / 100, 2, '.', '');
        $attributes = [
            'store_id' => $customer->store_id,
            'customer_id' => $customer->id,
            'received_by' => $attendant->id,
            'purpose' => 'creditos',
            'method' => $data['method'],
            'amount' => $amount,
            'reference' => $data['reference'] ?? null,
            'paid_at' => now(),
        ];

        if ($data['method'] === 'dinheiro') {
            $receivedInCents = isset($data['amount_received']) ? (int) round(((float) $data['amount_received']) * 100) : $amountInCents;

            if ($receivedInCents < $amountInCents) {
                throw ValidationException::withMessages(['amount_received' => 'O valor recebido não pode ser menor que o valor pago.']);
            }

            $attributes['amount_received'] = number_format($receivedInCents / 100, 2, '.', '');
            $attributes['change_amount'] = number_format(($receivedInCents - $amountInCents) / 100, 2, '.', '');
        }

        return DB::transaction(function () use ($customer, $attendant, $attributes, $amount): Payment {
            $payment = Payment::create($attributes);

            $customer->creditEntries()->create([
                'store_id' => $customer->store_id,
                'payment_id' => $payment->id,
                'created_by' => $attendant->id,
                'type' => 'compra',
                'amount' => $amount,
                'description' => 'Compra de créditos',
            ]);

            return $payment;
        });
    }
}
