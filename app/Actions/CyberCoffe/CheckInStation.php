<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Plan;
use App\Models\CyberCoffe\Station;
use App\Models\CyberCoffe\StationSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInStation
{
    /**
     * Open a session on a free station. Package plans are charged up front;
     * open plans snapshot the hourly rate and are billed at check-out.
     */
    public function handle(Station $station, Plan $plan, Attendant $attendant, ?Customer $customer): StationSession
    {
        return DB::transaction(function () use ($station, $plan, $attendant, $customer): StationSession {
            $lockedStation = Station::query()->whereKey($station->id)->lockForUpdate()->firstOrFail();

            if ($lockedStation->status !== 'livre') {
                throw ValidationException::withMessages([
                    'station_id' => 'A estação não está livre.',
                ]);
            }

            $startedAt = now();

            $session = StationSession::create([
                'store_id' => $lockedStation->store_id,
                'station_id' => $lockedStation->id,
                'customer_id' => $customer?->id,
                'opened_by' => $attendant->id,
                'billing' => $plan->billing,
                'hourly_rate' => $plan->isPackage() ? null : $plan->hourly_rate,
                'started_at' => $startedAt,
                'ends_at' => $plan->isPackage() ? $startedAt->addMinutes($plan->minutes) : null,
                'amount_due' => $plan->isPackage() ? $plan->price : '0.00',
                'status' => 'ativa',
            ]);

            if ($plan->isPackage()) {
                $session->charges()->create([
                    'store_id' => $session->store_id,
                    'plan_id' => $plan->id,
                    'description' => $plan->name,
                    'minutes' => $plan->minutes,
                    'amount' => $plan->price,
                ]);
            }

            $lockedStation->update(['status' => 'ocupada']);

            return $session;
        });
    }
}
