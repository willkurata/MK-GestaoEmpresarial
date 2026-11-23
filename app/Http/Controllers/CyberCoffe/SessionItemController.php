<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Actions\CyberCoffe\AddSessionItem;
use App\Actions\CyberCoffe\AddSessionPrints;
use App\Actions\CyberCoffe\RemoveSessionItem;
use App\Http\Resources\CyberCoffe\StationSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Extra lines on a session's bill: counter products (from the catalog or
 * with a manual price) and printed pages. Every response returns the
 * updated session with its itemized bill.
 */
class SessionItemController extends CyberCoffeController
{
    public function store(Request $request, int $session, AddSessionItem $addSessionItem): JsonResponse
    {
        $store = $this->currentStore($request);
        $session = $store->stationSessions()->findOrFail($session);
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'unit_price' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'description' => ['nullable', 'string', 'max:120'],
        ]);

        $product = isset($validated['product_id'])
            ? $store->products()->where('category', 'produto')->where('active', true)->whereKey($validated['product_id'])->firstOrFail()
            : null;

        $addSessionItem->handle($session, $product, [
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'unit_price' => $validated['unit_price'] ?? null,
            'description' => $validated['description'] ?? null,
        ], $this->currentAttendant($request));

        return $this->sessionResponse($request, $session->id, 201);
    }

    public function storePrints(Request $request, int $session, AddSessionPrints $addSessionPrints): JsonResponse
    {
        $session = $this->currentStore($request)->stationSessions()->findOrFail($session);
        $validated = $request->validate([
            'pages' => ['required', 'integer', 'min:1', 'max:1000'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $addSessionPrints->handle(
            $session,
            (int) $validated['pages'],
            isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            $this->currentAttendant($request),
        );

        return $this->sessionResponse($request, $session->id, 201);
    }

    public function destroy(Request $request, int $session, int $charge, RemoveSessionItem $removeSessionItem): JsonResponse
    {
        $session = $this->currentStore($request)->stationSessions()->findOrFail($session);

        $removeSessionItem->handle($session, $charge);

        return $this->sessionResponse($request, $session->id, 200);
    }

    private function sessionResponse(Request $request, int $sessionId, int $status): JsonResponse
    {
        $session = $this->currentStore($request)->stationSessions()
            ->with(['station', 'customer', 'charges'])
            ->findOrFail($sessionId);

        return (new StationSessionResource($session))->response()->setStatusCode($status);
    }
}
