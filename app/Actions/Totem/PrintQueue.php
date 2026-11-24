<?php

namespace App\Actions\Totem;

use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Hands paid orders to a totem agent one at a time, with a renewable lease.
 *
 * If the agent dies mid-job the lease expires and the scheduler puts the
 * order back in the queue (up to max_print_attempts), then refunds it.
 */
class PrintQueue
{
    public function __construct(private PrintOrderPayments $payments) {}

    /**
     * Give the agent its current job, or the next paid one. Null when the queue is empty.
     */
    public function claimNext(Totem $totem): ?PrintOrder
    {
        return DB::transaction(function () use ($totem): ?PrintOrder {
            $current = PrintOrder::query()
                ->where('totem_id', $totem->id)
                ->where('status', PrintOrderStatus::Printing)
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->forceFill(['lease_expires_at' => $this->leaseEnd()])->save();

                return $current;
            }

            $next = PrintOrder::query()
                ->where('totem_id', $totem->id)
                ->where('status', PrintOrderStatus::Queued)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $next?->transitionTo(PrintOrderStatus::Printing, 'totem', [
                'attempts' => $next->attempts + 1,
                'claimed_at' => now(),
                'lease_expires_at' => $this->leaseEnd(),
                'sheets_done' => 0,
            ]);

            return $next;
        });
    }

    public function reportProgress(PrintOrder $order, int $sheetsDone): void
    {
        $order->forceFill([
            'sheets_done' => min($sheetsDone, (int) $order->totalSheets()),
            'lease_expires_at' => $this->leaseEnd(),
        ])->save();
    }

    public function complete(PrintOrder $order, ?int $sheetsPrinted): void
    {
        $order->transitionTo(PrintOrderStatus::Ready, 'totem', [
            'sheets_done' => $order->totalSheets(),
            'ready_at' => now(),
            'lease_expires_at' => null,
        ], ['sheets_printed' => $sheetsPrinted]);

        $order->purgeFile();
    }

    /**
     * Retry a failed print while attempts remain; otherwise fail and refund the customer.
     */
    public function fail(PrintOrder $order, string $reason, bool $retryable, string $actor = 'totem'): void
    {
        if ($retryable && $order->attempts < (int) config('totem.max_print_attempts')) {
            $order->transitionTo(PrintOrderStatus::Queued, $actor, ['lease_expires_at' => null], ['reason' => $reason]);

            return;
        }

        $order->transitionTo(PrintOrderStatus::Failed, $actor, [
            'failure_reason' => $reason,
            'lease_expires_at' => null,
        ], ['reason' => $reason]);

        $this->payments->refund($order, $actor, $reason);
    }

    private function leaseEnd(): CarbonInterface
    {
        return now()->addSeconds((int) config('totem.lease_seconds'));
    }
}
