<?php

namespace App\Http\Controllers\Mk;

use App\Actions\Totem\PrintOrderPayments;
use App\Exceptions\InvalidPrintOrderTransition;
use App\Http\Controllers\Controller;
use App\Http\Resources\Totem\PrintOrderResource;
use App\Http\Resources\Totem\TotemResource;
use App\Models\PrintOrder;
use App\Models\Totem;
use App\PrintOrderStatus;
use App\TotemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * MK operations API for the print totems (JSON; the screens are built by the front).
 */
class TotemController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $totems = Totem::query()
            ->withCount([
                'printOrders as queued_orders_count' => fn ($query) => $query->where('status', PrintOrderStatus::Queued),
                'printOrders as printing_orders_count' => fn ($query) => $query->where('status', PrintOrderStatus::Printing),
                'printOrders as failed_orders_count' => fn ($query) => $query->where('status', PrintOrderStatus::Failed),
            ])
            ->orderBy('name')
            ->get();

        return TotemResource::collection($totems);
    }

    /**
     * Register a totem. The pairing code is shown once and typed into the agent.
     */
    public function store(Request $request): JsonResponse
    {
        $totem = Totem::query()->create([
            ...$this->validatedTotem($request),
            'code' => Totem::generateUniqueCode(),
            'status' => TotemStatus::Active,
        ]);

        return response()->json(['data' => [
            'totem' => new TotemResource($totem),
            'pairing_code' => $totem->issuePairingCode(),
            'pairing_code_expires_at' => $totem->pairing_code_expires_at->toIso8601String(),
        ]], 201);
    }

    public function update(Request $request, Totem $totem): TotemResource
    {
        abort_if($totem->status === TotemStatus::Revoked, 409, 'Totem revogado. Cadastre um novo totem.');

        $totem->update([
            ...$this->validatedTotem($request),
            ...$request->validate([
                'status' => ['sometimes', Rule::in([TotemStatus::Active->value, TotemStatus::Inactive->value])],
            ]),
        ]);

        return new TotemResource($totem);
    }

    /**
     * New pairing code, e.g. after replacing the totem's computer. The old device token keeps
     * working until the new agent pairs.
     */
    public function pairingCode(Totem $totem): JsonResponse
    {
        abort_if($totem->status === TotemStatus::Revoked, 409, 'Totem revogado. Cadastre um novo totem.');

        return response()->json(['data' => [
            'pairing_code' => $totem->issuePairingCode(),
            'pairing_code_expires_at' => $totem->pairing_code_expires_at->toIso8601String(),
        ]]);
    }

    /**
     * Stolen or compromised totem: the device token stops working immediately.
     */
    public function revoke(Totem $totem): TotemResource
    {
        $totem->forceFill([
            'status' => TotemStatus::Revoked,
            'api_token_hash' => null,
            'pairing_code_hash' => null,
            'pairing_code_expires_at' => null,
        ])->save();

        return new TotemResource($totem);
    }

    public function orders(Request $request, Totem $totem): AnonymousResourceCollection
    {
        $validated = $request->validate(['status' => ['nullable', Rule::enum(PrintOrderStatus::class)]]);

        $orders = $totem->printOrders()
            ->with(['totem', 'payment'])
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(50);

        return PrintOrderResource::collection($orders);
    }

    /**
     * Print a failed order again (e.g. after the jam was cleared), as long as it was paid.
     */
    public function requeue(Request $request, Totem $totem, PrintOrder $order): PrintOrderResource
    {
        $order = $this->totemOrder($totem, $order);

        abort_unless($order->payment?->status === 'paid', 409, 'Só pedidos pagos podem voltar para a fila.');

        $order->transitionTo(PrintOrderStatus::Queued, 'admin', ['failure_reason' => null, 'attempts' => 0], [
            'user_id' => $request->user()->id,
        ]);

        return new PrintOrderResource($order);
    }

    /**
     * Refund a paid order that will not be printed (queued or failed).
     */
    public function refund(Request $request, Totem $totem, PrintOrder $order, PrintOrderPayments $payments): PrintOrderResource
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $order = $this->totemOrder($totem, $order);

        if (! in_array($order->status, [PrintOrderStatus::Queued, PrintOrderStatus::Failed], true)) {
            throw new InvalidPrintOrderTransition($order->status, PrintOrderStatus::Refunded);
        }

        abort_unless($payments->refund($order, 'admin', $validated['reason']), 502, 'O estorno não foi confirmado pelo provedor Pix.');

        return new PrintOrderResource($order->refresh());
    }

    private function totemOrder(Totem $totem, PrintOrder $order): PrintOrder
    {
        abort_unless($order->totem_id === $totem->id, 404);

        return $order->load(['totem', 'payment']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTotem(Request $request): array
    {
        $isCreating = $request->isMethod('post');
        $required = $isCreating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'help_contact' => ['nullable', 'string', 'max:255'],
            'supports_color' => [$required, 'boolean'],
            'supports_duplex' => [$required, 'boolean'],
            'price_bw_per_side' => [$required, 'decimal:0,2', 'min:0.01', 'max:999'],
            'price_color_per_side' => ['nullable', 'required_if_accepted:supports_color', 'decimal:0,2', 'min:0.01', 'max:999'],
            'max_copies' => ['sometimes', 'integer', 'between:1,99'],
        ]);
    }
}
