<?php

namespace App\Http\Controllers\Totem;

use App\Actions\Totem\PrintQueue;
use App\Http\Controllers\Controller;
use App\Http\Resources\Totem\AgentPrintJobResource;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use App\TotemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API used by the local agent running on each totem (talks to CUPS/IPP or the Windows spooler).
 */
class AgentController extends Controller
{
    /**
     * Exchange the one-time pairing code shown in the MK panel for the device token.
     */
    public function pair(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'totem_code' => ['required', 'string', 'max:16'],
            'pairing_code' => ['required', 'string', 'max:16'],
        ]);

        $totem = Totem::query()
            ->where('code', strtoupper($validated['totem_code']))
            ->where('status', TotemStatus::Active)
            ->first();

        if ($totem === null || ! $totem->pairingCodeMatches($validated['pairing_code'])) {
            throw ValidationException::withMessages(['pairing_code' => 'Código de pareamento inválido ou expirado.']);
        }

        $token = $totem->issueApiToken();
        $totem->forceFill(['pairing_code_hash' => null, 'pairing_code_expires_at' => null, 'paired_at' => now()])->save();

        return response()->json(['data' => [
            'token' => $token,
            'totem' => ['code' => $totem->code, 'name' => $totem->name],
            'heartbeat_seconds' => 30,
            'poll_seconds' => 10,
        ]], 201);
    }

    public function rotateToken(Request $request): JsonResponse
    {
        return response()->json(['data' => ['token' => $this->totem($request)->issueApiToken()]]);
    }

    /**
     * Liveness plus printer status in IPP vocabulary (printer-state / printer-state-reasons).
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:32'],
            'printer' => ['required', 'array'],
            'printer.state' => ['required', Rule::in(['idle', 'processing', 'stopped'])],
            'printer.reasons' => ['present', 'array', 'max:20'],
            'printer.reasons.*' => ['string', 'max:64'],
            'printer.paper_level' => ['nullable', 'integer', 'between:0,100'],
            'printer.markers' => ['sometimes', 'array', 'max:8'],
            'printer.markers.*.color' => ['required', 'string', 'max:32'],
            'printer.markers.*.level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $totem = $this->totem($request);

        $totem->forceFill([
            'last_seen_at' => now(),
            'agent_version' => $validated['agent_version'] ?? $totem->agent_version,
            'printer_state' => $validated['printer']['state'],
            'printer_reasons' => array_values($validated['printer']['reasons']),
            'paper_level' => $validated['printer']['paper_level'] ?? null,
            'printer_markers' => array_values($validated['printer']['markers'] ?? []),
        ])->save();

        return response()->json(['data' => [
            'server_time' => now()->toIso8601String(),
            'queued' => $totem->printOrders()->where('status', PrintOrderStatus::Queued)->count(),
        ]]);
    }

    /**
     * Next job to print, holding a lease. The same job is returned until it is completed or failed.
     */
    public function claim(Request $request, PrintQueue $queue): AgentPrintJobResource|Response
    {
        $totem = $this->totem($request);
        $totem->forceFill(['last_seen_at' => now()])->save();

        $order = $queue->claimNext($totem);

        return $order === null ? response()->noContent() : new AgentPrintJobResource($order);
    }

    public function file(Request $request, PrintOrder $order): StreamedResponse
    {
        $order = $this->printingOrder($request, $order);

        abort_if($order->file_path === null, 410, 'O arquivo não está mais disponível.');

        return Storage::disk(config('totem.disk'))->download($order->file_path, $order->file_name, [
            'Content-Type' => $order->file_mime,
            'X-Content-SHA256' => $order->file_sha256,
        ]);
    }

    public function progress(Request $request, PrintOrder $order, PrintQueue $queue): AgentPrintJobResource
    {
        $validated = $request->validate(['sheets_done' => ['required', 'integer', 'min:0']]);
        $order = $this->printingOrder($request, $order);

        $queue->reportProgress($order, $validated['sheets_done']);

        return new AgentPrintJobResource($order);
    }

    /**
     * Printed. Safe to repeat: a retried call for an already finished job returns it unchanged.
     */
    public function complete(Request $request, PrintOrder $order, PrintQueue $queue): AgentPrintJobResource|JsonResponse
    {
        $validated = $request->validate(['sheets_printed' => ['nullable', 'integer', 'min:0']]);
        abort_unless($order->totem_id === $this->totem($request)->id, 404);

        if ($order->status === PrintOrderStatus::Ready) {
            return response()->json(['data' => ['id' => $order->public_id, 'status' => $order->status->value]]);
        }

        $order = $this->printingOrder($request, $order);
        $queue->complete($order, $validated['sheets_printed'] ?? null);

        return response()->json(['data' => ['id' => $order->public_id, 'status' => $order->status->value]]);
    }

    /**
     * Printing failed. Retryable failures (paper jam, out of paper) go back to the queue;
     * the rest fail and the customer is refunded.
     */
    public function fail(Request $request, PrintOrder $order, PrintQueue $queue): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'retryable' => ['required', 'boolean'],
        ]);

        $order = $this->printingOrder($request, $order);
        $queue->fail($order, $validated['reason'], (bool) $validated['retryable']);
        $order->refresh();

        return response()->json(['data' => ['id' => $order->public_id, 'status' => $order->status->value]]);
    }

    private function totem(Request $request): Totem
    {
        /** @var Totem */
        return $request->user('totem');
    }

    /**
     * A totem only sees its own jobs, and only while it holds them.
     */
    private function printingOrder(Request $request, PrintOrder $order): PrintOrder
    {
        abort_unless($order->totem_id === $this->totem($request)->id, 404);
        abort_unless($order->status === PrintOrderStatus::Printing, 409, 'Este trabalho não está em impressão.');

        return $order;
    }
}
