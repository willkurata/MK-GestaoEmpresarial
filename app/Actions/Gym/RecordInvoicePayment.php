<?php

namespace App\Actions\Gym;

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settles a pending invoice with a single payment for its full amount.
 */
class RecordInvoicePayment
{
    /**
     * @param  array{method: string, amount: string, source: string, paid_at: CarbonInterface, gateway_payment_id?: string|null, recorded_by_user_id?: int|null}  $data
     */
    public function handle(GymInvoice $invoice, array $data): GymPayment
    {
        return DB::transaction(function () use ($invoice, $data): GymPayment {
            /** @var GymInvoice $invoice */
            $invoice = GymInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== GymInvoice::STATUS_PENDING) {
                throw ValidationException::withMessages(['invoice' => 'Esta mensalidade não está pendente.']);
            }

            if (self::toCents($data['amount']) !== self::toCents((string) $invoice->amount)) {
                throw ValidationException::withMessages(['amount' => 'O valor deve ser igual ao da mensalidade.']);
            }

            $payment = $invoice->payments()->create([
                'gym_id' => $invoice->gym_id,
                'method' => $data['method'],
                'amount' => $invoice->amount,
                'status' => GymPayment::STATUS_CONFIRMED,
                'source' => $data['source'],
                'gateway_payment_id' => $data['gateway_payment_id'] ?? null,
                'recorded_by_user_id' => $data['recorded_by_user_id'] ?? null,
                'paid_at' => $data['paid_at'],
            ]);

            $invoice->forceFill(['status' => GymInvoice::STATUS_PAID, 'paid_at' => $data['paid_at']])->save();

            return $payment;
        });
    }

    public static function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
