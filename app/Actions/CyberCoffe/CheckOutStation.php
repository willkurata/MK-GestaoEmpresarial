<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutStation
{
    /**
     * Close an active session and free the station. Open sessions are billed
     * per started minute at the snapshotted hourly rate.
     */
    public function handle(StationSession $session, Attendant $attendant): StationSession
    {
        return DB::transaction(function () use ($session, $attendant): StationSession {
            $locked = StationSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['session' => 'A sessão já foi encerrada.']);
            }

            $closedAt = now();
            $attributes = ['closed_at' => $closedAt, 'closed_by' => $attendant->id, 'status' => 'encerrada'];

            if (! $locked->isPackage()) {
                $minutes = $locked->billableMinutes($closedAt);
                $amount = number_format($locked->openAmountInCents($closedAt) / 100, 2, '.', '');
                $attributes['amount_due'] = $amount;

                $locked->charges()->create([
                    'store_id' => $locked->store_id,
                    'description' => "Tempo livre ({$minutes} min)",
                    'minutes' => $minutes,
                    'amount' => $amount,
                ]);
            }

            $locked->update($attributes);

            Station::query()->whereKey($locked->station_id)->where('status', 'ocupada')->update(['status' => 'livre']);

            return $locked;
        });
    }
}
