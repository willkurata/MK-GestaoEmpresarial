<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Actions\CyberCoffe\PurchaseCredits;
use App\Http\Resources\CyberCoffe\CustomerResource;
use App\Http\Resources\CyberCoffe\PaymentResource;
use App\Models\CyberCoffe\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CustomerController extends CyberCoffeController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));

        $customers = $this->currentStore($request)->customers()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $search).'%')))
            ->orderBy('name')
            ->paginate(25);

        return CustomerResource::collection($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:10,13', Rule::unique('cc_customers')->where('store_id', $store->id)],
            'document' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);

        $customer = $store->customers()->create([...$validated, 'name' => trim($validated['name'])]);

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $customer): CustomerResource
    {
        return new CustomerResource($this->currentStore($request)->customers()->findOrFail($customer));
    }

    public function update(Request $request, int $customer): CustomerResource
    {
        $store = $this->currentStore($request);
        $customer = $store->customers()->findOrFail($customer);

        if ($request->has('phone')) {
            $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'digits_between:10,13', Rule::unique('cc_customers')->where('store_id', $store->id)->ignore($customer->id)],
            'document' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ]);

        $customer->update($validated);

        if (($validated['status'] ?? null) === 'inativo') {
            $customer->apiTokens()->delete();
        }

        return new CustomerResource($customer->fresh());
    }

    public function credits(Request $request, int $customer): JsonResponse
    {
        $customer = $this->currentStore($request)->customers()->findOrFail($customer);

        return response()->json([
            'balance' => number_format($customer->creditBalanceInCents() / 100, 2, '.', ''),
            'entries' => $customer->creditEntries()->latest('id')->limit(100)->get(['id', 'type', 'amount', 'description', 'payment_id', 'created_at']),
        ]);
    }

    public function purchaseCredits(Request $request, int $customer, PurchaseCredits $purchaseCredits): JsonResponse
    {
        $customer = $this->currentStore($request)->customers()->findOrFail($customer);
        $validated = $request->validate([
            'method' => ['required', Rule::in(Payment::REVENUE_METHODS)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'amount_received' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payment = $purchaseCredits->handle($customer, $this->currentAttendant($request), $validated);

        return response()->json([
            'payment' => new PaymentResource($payment),
            'balance' => number_format($customer->creditBalanceInCents() / 100, 2, '.', ''),
        ], 201);
    }
}
