<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\StationSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveSessionItem
{
    /**
     * Remove a product or print line added by mistake. Only allowed while the
     * session is active and only if what was already paid still fits in the
     * new amount due; time charges are never removed.
     */
    public function handle(StationSession $session, int $chargeId): StationSession
    {
        return DB::transaction(function () use ($session, $chargeId): StationSession {
            $locked = StationSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $charge = $locked->charges()->whereKey($chargeId)->firstOrFail();

            if ($charge->isTime()) {
                throw ValidationException::withMessages(['charge' => 'Cobranças de tempo não podem ser removidas.']);
            }

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['charge' => 'Só é possível remover itens de uma sessão ativa.']);
            }

            $newAmountDueInCents = $locked->amountDueInCents() - $charge->amountInCents();

            if ($newAmountDueInCents < $locked->amountPaidInCents()) {
                throw ValidationException::withMessages(['charge' => 'O item já foi pago e não pode ser removido.']);
            }

            $charge->delete();
            $locked->update(['amount_due' => number_format($newAmountDueInCents / 100, 2, '.', '')]);

            return $locked;
        });
    }
}
