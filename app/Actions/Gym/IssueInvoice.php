<?php

namespace App\Actions\Gym;

use App\Actions\Gym\Payments\PaymentGateway;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates the invoice for the subscription's next billing period and advances the billing date.
 */
class IssueInvoice
{
    public function __construct(private PaymentGateway $gateway) {}

    public function handle(GymSubscription $subscription): GymInvoice
    {
        return DB::transaction(function () use ($subscription): GymInvoice {
            /** @var GymSubscription $subscription */
            $subscription = GymSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $periodStart = CarbonImmutable::parse($subscription->next_billing_on);
            $nextBillingOn = $periodStart->addMonthsNoOverflow($subscription->billing_interval_months);

            $invoice = $subscription->invoices()->create([
                'gym_id' => $subscription->gym_id,
                'gym_student_id' => $subscription->gym_student_id,
                'amount' => $subscription->price,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $nextBillingOn->subDay()->toDateString(),
                'due_on' => $periodStart->toDateString(),
                'status' => GymInvoice::STATUS_PENDING,
                'payment_method' => $subscription->payment_method,
            ]);

            $charge = $this->gateway->createCharge($invoice);
            $invoice->forceFill([
                'gateway_reference' => $charge['reference'],
                'payment_instructions' => $charge['instructions'],
            ])->save();

            $subscription->forceFill(['next_billing_on' => $nextBillingOn->toDateString()])->save();

            return $invoice;
        });
    }
}
