<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Actions\CyberCoffe\CheckInStation;
use App\Actions\CyberCoffe\CheckOutStation;
use App\Actions\CyberCoffe\ExtendSession;
use App\Actions\CyberCoffe\RegisterSessionPayment;
use App\Http\Resources\CyberCoffe\PaymentResource;
use App\Http\Resources\CyberCoffe\StationSessionResource;
use App\Models\CyberCoffe\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StationSessionController extends CyberCoffeController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['ativa', 'encerrada'])],
            'station_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $sessions = $this->currentStore($request)->stationSessions()
            ->with(['station', 'customer'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('station_id'), fn ($query, $stationId) => $query->where('station_id', $stationId))
            ->when($request->query('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->latest('started_at')
            ->paginate(25);

        return StationSessionResource::collection($sessions);
    }

    public function show(Request $request, int $session): StationSessionResource
    {
        return new StationSessionResource(
            $this->currentStore($request)->stationSessions()->with(['station', 'customer', 'charges'])->findOrFail($session),
        );
    }

    public function checkIn(Request $request, CheckInStation $checkInStation): JsonResponse
    {
        $store = $this->currentStore($request);
        $validated = $request->validate([
            'station_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $station = $store->stations()->whereKey($validated['station_id'])->firstOrFail();
        $plan = $store->plans()->where('active', true)->whereKey($validated['plan_id'])->firstOrFail();
        $customer = isset($validated['customer_id'])
            ? $store->customers()->where('status', 'ativo')->whereKey($validated['customer_id'])->firstOrFail()
            : null;

        $session = $checkInStation->handle($station, $plan, $this->currentAttendant($request), $customer);

        return (new StationSessionResource($session->load(['station', 'customer', 'charges'])))
            ->response()
            ->setStatusCode(201);
    }

    public function extend(Request $request, int $session, ExtendSession $extendSession): StationSessionResource
    {
        $store = $this->currentStore($request);
        $session = $store->stationSessions()->findOrFail($session);
        $validated = $request->validate(['plan_id' => ['required', 'integer']]);
        $plan = $store->plans()->where('active', true)->whereKey($validated['plan_id'])->firstOrFail();

        $session = $extendSession->handle($session, $plan);

        return new StationSessionResource($session->load(['station', 'customer', 'charges']));
    }

    public function checkOut(Request $request, int $session, CheckOutStation $checkOutStation): StationSessionResource
    {
        $session = $this->currentStore($request)->stationSessions()->findOrFail($session);

        $session = $checkOutStation->handle($session, $this->currentAttendant($request));

        return new StationSessionResource($session->load(['station', 'customer', 'charges']));
    }

    public function pay(Request $request, int $session, RegisterSessionPayment $registerSessionPayment): JsonResponse
    {
        $session = $this->currentStore($request)->stationSessions()->findOrFail($session);
        $validated = $request->validate([
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'amount_received' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payment = $registerSessionPayment->handle($session, $this->currentAttendant($request), $validated);

        return response()->json([
            'payment' => new PaymentResource($payment),
            'session' => new StationSessionResource($session->fresh()),
        ], 201);
    }
}
