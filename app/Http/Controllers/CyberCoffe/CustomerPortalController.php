<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Http\Resources\CyberCoffe\CustomerResource;
use App\Http\Resources\CyberCoffe\StationSessionResource;
use App\Models\CyberCoffe\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only endpoints for the customer's own app/totem.
 */
class CustomerPortalController extends CyberCoffeController
{
    public function me(Request $request): CustomerResource
    {
        return new CustomerResource($this->customer($request));
    }

    public function credits(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        return response()->json([
            'balance' => number_format($customer->creditBalanceInCents() / 100, 2, '.', ''),
            'entries' => $customer->creditEntries()->latest('id')->limit(50)->get(['id', 'type', 'amount', 'description', 'created_at']),
        ]);
    }

    public function sessions(Request $request): AnonymousResourceCollection
    {
        return StationSessionResource::collection(
            $this->customer($request)->stationSessions()->with('station')->latest('started_at')->paginate(25),
        );
    }

    public function currentSession(Request $request): JsonResponse
    {
        $session = $this->customer($request)->stationSessions()->with('station')->where('status', 'ativa')->latest('started_at')->first();

        return response()->json(['data' => $session ? new StationSessionResource($session) : null]);
    }

    private function customer(Request $request): Customer
    {
        return $request->attributes->get('cybercoffe.user');
    }
}
