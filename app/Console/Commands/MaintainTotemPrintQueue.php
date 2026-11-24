<?php

namespace App\Console\Commands;

use App\Actions\Totem\PrintQueue;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use App\TotemStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('totem:maintain')]
#[Description('Keep the print totems running unattended: expire unpaid orders, recover stuck jobs and purge customer files')]
class MaintainTotemPrintQueue extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PrintQueue $queue): int
    {
        $expired = $this->expireUnpaidOrders();
        $recovered = $this->recoverExpiredLeases($queue);
        $purged = $this->purgeFiles($queue);
        $offline = $this->reportOfflineTotems();

        $this->info("Expirados: {$expired} · Recuperados: {$recovered} · Arquivos apagados: {$purged} · Totens offline: {$offline}");

        return self::SUCCESS;
    }

    /**
     * Drafts and unpaid charges past their deadline. A late payment is refunded by the webhook.
     */
    private function expireUnpaidOrders(): int
    {
        $expired = 0;

        PrintOrder::query()
            ->whereIn('status', [PrintOrderStatus::Draft, PrintOrderStatus::AwaitingPayment])
            ->where('expires_at', '<=', now())
            ->each(function (PrintOrder $order) use (&$expired): void {
                DB::transaction(function () use ($order, &$expired): void {
                    $order = PrintOrder::query()->lockForUpdate()->find($order->id);

                    if (! in_array($order?->status, [PrintOrderStatus::Draft, PrintOrderStatus::AwaitingPayment], true)) {
                        return;
                    }

                    $order->transitionTo(PrintOrderStatus::Expired, 'system');
                    $order->payment()->where('status', 'pending')->update(['status' => 'expired']);
                    $expired++;
                });

                $order->refresh()->purgeFile();
            });

        return $expired;
    }

    /**
     * Jobs whose agent stopped answering (crash, power or network loss) go back to the queue.
     */
    private function recoverExpiredLeases(PrintQueue $queue): int
    {
        $recovered = 0;

        PrintOrder::query()
            ->where('status', PrintOrderStatus::Printing)
            ->where('lease_expires_at', '<=', now())
            ->each(function (PrintOrder $order) use ($queue, &$recovered): void {
                $queue->fail($order, 'O totem parou de responder durante a impressão.', retryable: true, actor: 'system');
                $recovered++;
            });

        return $recovered;
    }

    /**
     * LGPD: no customer file outlives its retention deadline, whatever the order status.
     * A paid order still waiting in the queue can no longer be printed, so it is refunded.
     */
    private function purgeFiles(PrintQueue $queue): int
    {
        $purged = 0;

        PrintOrder::query()
            ->whereNotNull('file_path')
            ->where('file_purge_at', '<=', now())
            ->where('status', '!=', PrintOrderStatus::Printing)
            ->each(function (PrintOrder $order) use ($queue, &$purged): void {
                if ($order->status === PrintOrderStatus::Queued) {
                    $queue->fail($order, 'O arquivo passou do prazo de retenção sem ser impresso.', retryable: false, actor: 'system');
                }

                $order->refresh()->purgeFile();
                $purged++;
            });

        return $purged;
    }

    private function reportOfflineTotems(): int
    {
        $offlineTotems = Totem::query()
            ->where('status', TotemStatus::Active)
            ->whereNotNull('paired_at')
            ->where(fn ($query) => $query
                ->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subSeconds((int) config('totem.offline_after_seconds'))))
            ->pluck('code');

        if ($offlineTotems->isNotEmpty()) {
            Log::warning('Totens de impressão offline.', ['totems' => $offlineTotems->all()]);
        }

        return $offlineTotems->count();
    }
}
