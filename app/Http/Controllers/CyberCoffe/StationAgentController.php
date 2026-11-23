<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Http\Resources\CyberCoffe\StationSessionResource;
use App\Models\CyberCoffe\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for the desktop agent running on each station. The agent locks
 * the machine when there is no active session or the time has run out.
 */
class StationAgentController extends CyberCoffeController
{
    public function heartbeat(Request $request): JsonResponse
    {
        $station = $this->station($request);
        $station->forceFill(['last_seen_at' => now()])->save();

        return $this->sessionResponse($station);
    }

    public function session(Request $request): JsonResponse
    {
        return $this->sessionResponse($this->station($request));
    }

    private function sessionResponse(Station $station): JsonResponse
    {
        $session = $station->activeSession()->with('customer')->first();
        $unlocked = $session !== null && ! $session->isExpired();

        return response()->json([
            'station' => ['id' => $station->id, 'name' => $station->name, 'status' => $station->status],
            'locked' => ! $unlocked,
            'session' => $session ? new StationSessionResource($session) : null,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function station(Request $request): Station
    {
        return $request->attributes->get('cybercoffe.user');
    }
}
