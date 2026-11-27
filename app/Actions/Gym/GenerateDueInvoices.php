<?php

namespace App\Actions\Gym;

use App\Models\Gym\GymSubscription;

/**
 * Generates every recurring invoice whose billing date falls within the configured lead time.
 * Safe to run repeatedly: each run only bills periods not yet billed.
 */
class GenerateDueInvoices
{
    public function __construct(private IssueInvoice $issueInvoice) {}

    /**
     * @return int Number of invoices created.
     */
    public function handle(): int
    {
        $billingLimit = today()->addDays((int) config('academia.invoice_lead_days'));
        $created = 0;

        GymSubscription::query()
            ->where('status', 'ativa')
            ->whereDate('next_billing_on', '<=', $billingLimit)
            ->chunkById(200, function ($subscriptions) use ($billingLimit, &$created): void {
                foreach ($subscriptions as $subscription) {
                    while ($subscription->next_billing_on->lte($billingLimit)) {
                        $this->issueInvoice->handle($subscription);
                        $subscription->refresh();
                        $created++;
                    }
                }
            });

        return $created;
    }
}
