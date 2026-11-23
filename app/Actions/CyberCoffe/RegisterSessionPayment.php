<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Payment;
use App\Models\CyberCoffe\StationSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterSessionPayment
{
    /**
     * Register a payment against a session's outstanding balance. Paying with
     * "creditos" debits the customer's ledger under a row lock.
     *
     * @param  array{method: string, amount?: string|null, amount_received?: string|null, reference?: string|null}  $data
     */
    public function handle(StationSession $session, Attendant $attendant, array $data): Payment
    {
        return DB::transaction(function () use ($session, $attendant, $data): Payment {
            $locked = StationSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $outstandingInCents = $locked->amountDueInCents() - $locked->amountPaidInCents();

            if ($outstandingInCents <= 0) {
                throw ValidationException::withMessages(['amount' => 'Não há valor em aberto nesta sessão.']);
            }

            $amountInCents = isset($data['amount']) ? (int) round(((float) $data['amount']) * 100) : $outstandingInCents;

            if ($amountInCents <= 0 || $amountInCents > $outstandingInCents) {
                throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero e no máximo o valor em aberto.']);
            }

            $amount = number_format($amountInCents / 100, 2, '.', '');
            $attributes = [
                'store_id' => $locked->store_id,
                'customer_id' => $locked->customer_id,
                'station_session_id' => $locked->id,
                'received_by' => $attendant->id,
                'purpose' => 'sessao',
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

            if ($data['method'] === 'creditos') {
                $this->ensureCustomerHasCredits($locked, $amountInCents);
            }

            $payment = Payment::create($attributes);

            if ($data['method'] === 'creditos') {
                $payment->customer->creditEntries()->create([
                    'store_id' => $locked->store_id,
                    'payment_id' => $payment->id,
                    'created_by' => $attendant->id,
                    'type' => 'consumo',
                    'amount' => '-'.$amount,
                    'description' => "Sessão #{$locked->id}",
                ]);
            }

            return $payment;
        });
    }

    private function ensureCustomerHasCredits(StationSession $session, int $amountInCents): void
    {
        if ($session->customer_id === null) {
            throw ValidationException::withMessages(['method' => 'Pagamento com créditos exige um cliente vinculado à sessão.']);
        }

        $customer = Customer::query()->whereKey($session->customer_id)->lockForUpdate()->firstOrFail();

        if ($customer->creditBalanceInCents() < $amountInCents) {
            throw ValidationException::withMessages(['method' => 'Saldo de créditos insuficiente.']);
        }
    }
}
