<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Company $company */
        $company = $request->user('company');

        return Inertia::render('Products/Index', [
            'products' => $company->products()
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product): array => [
                    ...$product->toArray(),
                    'active' => $product->status === 'ativo',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sale_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $active = $validated['active'] ?? true;
        unset($validated['active']);

        $company->products()->create([
            ...$validated,
            'name' => trim($validated['name']),
            'status' => $active ? 'ativo' : 'inativo',
        ]);

        return back()->with('success', 'Produto cadastrado.');
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        $product = $company->products()->findOrFail($product);
        if ($request->has('active') && ! $request->has('status')) {
            $request->merge(['status' => $request->boolean('active') ? 'ativo' : 'inativo']);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sale_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'status' => ['required', Rule::in(['ativo', 'inativo'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        unset($validated['active']);

        $product->update([
            ...$validated,
            'name' => trim($validated['name']),
        ]);

        return back()->with('success', 'Produto atualizado.');
    }
}
