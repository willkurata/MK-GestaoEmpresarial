<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Http\Resources\CyberCoffe\ProductResource;
use App\Models\CyberCoffe\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Counter catalog: goods (water, soda, snacks) and prices per printed page.
 * Prices may change freely because every session line snapshots its price.
 */
class ProductController extends CyberCoffeController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(Product::CATEGORIES)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $products = $this->currentStore($request)->products()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('active', true))
            ->when($validated['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('cc_products')->where('store_id', $store->id)],
            'category' => ['sometimes', Rule::in(Product::CATEGORIES)],
            'price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $product = $store->products()->create([...$validated, 'name' => trim($validated['name'])]);

        return (new ProductResource($product->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $product): ProductResource
    {
        $store = $this->currentStore($request);
        $product = $store->products()->findOrFail($product);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('cc_products')->where('store_id', $store->id)->ignore($product->id)],
            'category' => ['sometimes', Rule::in(Product::CATEGORIES)],
            'price' => ['sometimes', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['name'] = trim($validated['name']);
        }

        $product->update($validated);

        return new ProductResource($product);
    }
}
