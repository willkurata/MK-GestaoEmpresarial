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
                    'low_stock' => $product->isLowStock(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->where('company_id', $company->id)],
            'sale_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            ...$this->stockRules(),
            'active' => ['sometimes', 'boolean'],
        ]);
        $active = $validated['active'] ?? true;
        unset($validated['active']);

        $company->products()->create([
            ...$validated,
            ...$this->normalizeStock($validated),
            'name' => trim($validated['name']),
            'barcode' => $this->normalizeBarcode($validated['barcode'] ?? null),
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
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->where('company_id', $company->id)->ignore($product->id),
            ],
            'sale_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            ...$this->stockRules(),
            'status' => ['required', Rule::in(['ativo', 'inativo'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        unset($validated['active']);
        $barcode = array_key_exists('barcode', $validated)
            ? $this->normalizeBarcode($validated['barcode'])
            : $product->barcode;

        $product->update([
            ...$validated,
            ...$this->normalizeStock($validated),
            'name' => trim($validated['name']),
            'barcode' => $barcode,
        ]);

        return back()->with('success', 'Produto atualizado.');
    }

    /**
     * Regras do controle opcional de estoque (quantidades inteiras na V1).
     *
     * @return array<string, array<int, string>>
     */
    private function stockRules(): array
    {
        return [
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Sem estoque controlado, o limite de alerta não se aplica.
     *
     * @param  array<string, mixed>  $validated
     * @return array{stock_quantity?: int|null, low_stock_threshold?: int|null}
     */
    private function normalizeStock(array $validated): array
    {
        if (! array_key_exists('stock_quantity', $validated)) {
            return [];
        }

        $stockQuantity = $validated['stock_quantity'] === null ? null : (int) $validated['stock_quantity'];
        $threshold = $validated['low_stock_threshold'] ?? null;

        return [
            'stock_quantity' => $stockQuantity,
            'low_stock_threshold' => $stockQuantity === null || $threshold === null ? null : (int) $threshold,
        ];
    }

    private function normalizeBarcode(?string $barcode): ?string
    {
        $barcode = trim($barcode ?? '');

        return $barcode === '' ? null : $barcode;
    }
}
