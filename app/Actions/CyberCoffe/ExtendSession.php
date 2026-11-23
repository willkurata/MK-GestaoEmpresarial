<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\Plan;
use App\Models\CyberCoffe\StationSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExtendSession
{
    /**
     * Add a package's minutes to an active package session. If the time had
     * already run out, the new minutes count from now.
     */
    public function handle(StationSession $session, Plan $plan): StationSession
    {
        if (! $plan->isPackage()) {
            throw ValidationException::withMessages(['plan_id' => 'Só é possível adicionar tempo com um plano do tipo pacote.']);
        }

        return DB::transaction(function () use ($session, $plan): StationSession {
            $locked = StationSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive() || ! $locked->isPackage()) {
                throw ValidationException::withMessages(['session' => 'Só é possível adicionar tempo a uma sessão de pacote ativa.']);
            }

            $base = $locked->ends_at->isFuture() ? $locked->ends_at : now();
            $amountDueInCents = $locked->amountDueInCents() + (int) round(((float) $plan->price) * 100);

            $locked->update([
                'ends_at' => $base->addMinutes($plan->minutes),
                'amount_due' => number_format($amountDueInCents / 100, 2, '.', ''),
            ]);

            $locked->charges()->create([
                'store_id' => $locked->store_id,
                'plan_id' => $plan->id,
                'kind' => 'tempo',
                'description' => $plan->name,
                'minutes' => $plan->minutes,
                'amount' => $plan->price,
            ]);

            return $locked;
        });
    }
}
