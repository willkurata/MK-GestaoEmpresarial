<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Actions\CyberCoffe\AddSessionPrints;
use App\Http\Resources\CyberCoffe\StationSessionResource;
use App\Models\CyberCoffe\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    /**
     * Pages printed from this station, reported by the agent (e.g. from the
     * Windows print spooler). "job_id" makes retries idempotent.
     */
    public function prints(Request $request, AddSessionPrints $addSessionPrints): JsonResponse
    {
        $station = $this->station($request);
        $validated = $request->validate([
            'pages' => ['required', 'integer', 'min:1', 'max:1000'],
            'product_id' => ['nullable', 'integer'],
            'job_id' => ['nullable', 'string', 'max:100'],
        ]);

        $session = $station->activeSession()->first();

        if ($session === null) {
            throw ValidationException::withMessages(['session' => 'Nenhuma sessão ativa nesta estação.']);
        }

        $addSessionPrints->handle(
            $session,
            (int) $validated['pages'],
            isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            null,
            isset($validated['job_id']) ? 'agent:'.$validated['job_id'] : null,
        );

        return $this->sessionResponse($station, 201);
    }

    private function sessionResponse(Station $station, int $status = 200): JsonResponse
    {
        $session = $station->activeSession()->with(['customer', 'charges'])->first();
        $unlocked = $session !== null && ! $session->isExpired();

        return response()->json([
            'station' => ['id' => $station->id, 'name' => $station->name, 'status' => $station->status],
            'locked' => ! $unlocked,
            'session' => $session ? new StationSessionResource($session) : null,
            'server_time' => now()->toIso8601String(),
        ], $status);
    }

    private function station(Request $request): Station
    {
        return $request->attributes->get('cybercoffe.user');
    }
}
