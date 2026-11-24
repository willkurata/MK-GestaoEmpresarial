<?php

namespace App\Http\Controllers\Totem;

use App\Actions\Totem\Pix\PixGateway;
use App\Actions\Totem\PrintOrderPayments;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PixWebhookController extends Controller
{
    /**
     * PSP notification. The signature is checked by the gateway and the payment is
     * confirmed with the PSP before any order is released for printing.
     */
    public function __invoke(Request $request, PixGateway $gateway, PrintOrderPayments $payments): JsonResponse
    {
        $externalId = $gateway->paidChargeFromWebhook($request);

        if ($externalId === null) {
            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }

        $order = $payments->confirm($externalId);

        return response()->json(['data' => [
            'received' => true,
            'order_status' => $order?->status->value,
        ]]);
    }
}
