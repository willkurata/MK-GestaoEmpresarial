<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Http\Resources\CyberCoffe\StationResource;
use App\Models\CyberCoffe\ApiToken;
use App\Models\CyberCoffe\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StationController extends CyberCoffeController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $stations = $this->currentStore($request)->stations()
            ->with('activeSession.customer')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->get();

        return StationResource::collection($stations);
    }

    public function show(Request $request, int $station): StationResource
    {
        return new StationResource(
            $this->currentStore($request)->stations()->with('activeSession.customer')->findOrFail($station),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('cc_stations')->where('store_id', $store->id)],
            'type' => ['required', Rule::in(Station::TYPES)],
        ]);

        $station = $store->stations()->create([...$validated, 'name' => trim($validated['name']), 'status' => 'livre']);

        return (new StationResource($station))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $station): StationResource
    {
        $store = $this->currentStore($request);
        $station = $store->stations()->findOrFail($station);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:60', Rule::unique('cc_stations')->where('store_id', $store->id)->ignore($station->id)],
            'type' => ['sometimes', Rule::in(Station::TYPES)],
        ]);

        $station->update($validated);

        return new StationResource($station);
    }

    /**
     * Toggle a station between free and maintenance. "ocupada" is controlled
     * only by check-in/check-out.
     */
    public function updateStatus(Request $request, int $station): StationResource
    {
        $station = $this->currentStore($request)->stations()->findOrFail($station);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['livre', 'manutencao'])],
        ]);

        $updated = DB::transaction(function () use ($station, $validated): Station {
            $locked = Station::query()->whereKey($station->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ocupada') {
                throw ValidationException::withMessages(['status' => 'Encerre a sessão ativa antes de alterar o status da estação.']);
            }

            $locked->update(['status' => $validated['status']]);

            return $locked;
        });

        return new StationResource($updated);
    }

    /**
     * Issue the token used by the station's desktop agent. Older agent tokens
     * for the station are revoked.
     */
    public function issueAgentToken(Request $request, int $station): JsonResponse
    {
        $station = $this->currentStore($request)->stations()->findOrFail($station);
        $station->apiTokens()->delete();

        return response()->json([
            'token' => ApiToken::issue($station, 'agente', null),
            'token_type' => 'Bearer',
        ], 201);
    }
}
