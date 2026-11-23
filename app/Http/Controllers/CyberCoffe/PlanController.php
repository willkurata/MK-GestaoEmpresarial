<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Models\CyberCoffe\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends CyberCoffeController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->currentStore($request)->plans()
                ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('active', true))
                ->orderBy('billing')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'billing' => ['required', Rule::in(Plan::BILLINGS)],
            'price' => ['required_if:billing,pacote', 'prohibited_if:billing,avulso', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'minutes' => ['required_if:billing,pacote', 'prohibited_if:billing,avulso', 'nullable', 'integer', 'min:1', 'max:1440'],
            'hourly_rate' => ['required_if:billing,avulso', 'prohibited_if:billing,pacote', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'billing_increment_minutes' => ['prohibited_if:billing,pacote', 'nullable', 'integer', Rule::in(Plan::BILLING_INCREMENTS)],
            'active' => ['sometimes', 'boolean'],
        ]);

        if ($validated['billing'] === 'avulso') {
            $validated['billing_increment_minutes'] ??= 1;
        }

        $plan = $this->currentStore($request)->plans()->create([...$validated, 'name' => trim($validated['name'])]);

        return response()->json(['data' => $plan->fresh()], 201);
    }

    /**
     * Only name and availability can change: prices are snapshotted on each
     * session, so a new price should be a new plan.
     */
    public function update(Request $request, int $plan): JsonResponse
    {
        $plan = $this->currentStore($request)->plans()->findOrFail($plan);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $plan->update($validated);

        return response()->json(['data' => $plan]);
    }
}
