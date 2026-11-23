<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Http\Resources\CyberCoffe\PaymentResource;
use App\Models\CyberCoffe\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PaymentController extends CyberCoffeController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'method' => ['nullable', Rule::in(Payment::METHODS)],
            'purpose' => ['nullable', Rule::in(['sessao', 'creditos'])],
        ]);

        $payments = $this->currentStore($request)->payments()
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->where('paid_at', '>=', $from.' 00:00:00'))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->where('paid_at', '<=', $to.' 23:59:59'))
            ->when($validated['method'] ?? null, fn ($query, $method) => $query->where('method', $method))
            ->when($validated['purpose'] ?? null, fn ($query, $purpose) => $query->where('purpose', $purpose))
            ->latest('paid_at')
            ->paginate(50);

        return PaymentResource::collection($payments);
    }
}
