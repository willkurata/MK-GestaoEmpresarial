<?php

namespace App\Actions\Gym;

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Full refund: keeps the payment, marks it as refunded and reopens the invoice so it counts as unpaid again.
 */
class RefundPayment
{
    public function handle(GymPayment $payment, string $reason, ?int $refundedByUserId): GymPayment
    {
        return DB::transaction(function () use ($payment, $reason, $refundedByUserId): GymPayment {
            /** @var GymPayment $payment */
            $payment = GymPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === GymPayment::STATUS_REFUNDED) {
                throw ValidationException::withMessages(['payment' => 'Este pagamento já foi estornado.']);
            }

            $payment->forceFill([
                'status' => GymPayment::STATUS_REFUNDED,
                'refunded_at' => now(),
                'refund_reason' => $reason,
                'refunded_by_user_id' => $refundedByUserId,
            ])->save();

            $payment->invoice()->update(['status' => GymInvoice::STATUS_PENDING, 'paid_at' => null]);

            return $payment;
        });
    }
}
