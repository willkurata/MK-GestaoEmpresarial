<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    /**
     * Lista o estoque atual dos produtos da empresa, com alertas de estoque baixo.
     */
    public function __invoke(Request $request): Response
    {
        /** @var Company $company */
        $company = $request->user('company');

        $products = $company->products()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'active' => $product->status === 'ativo',
                'tracks_stock' => $product->tracksStock(),
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'low_stock' => $product->isLowStock(),
            ]);

        return Inertia::render('Stock/Index', [
            'products' => $products->sortByDesc('low_stock')->values(),
            'summary' => [
                'tracked' => $products->where('tracks_stock', true)->count(),
                'untracked' => $products->where('tracks_stock', false)->count(),
                'lowStock' => $products->where('low_stock', true)->count(),
            ],
        ]);
    }
}
