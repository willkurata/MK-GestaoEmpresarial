<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vitrine pública de imóveis.
 *
 * O frontend já consome o contrato descrito em resources/js/types/site.ts.
 * O backend (WIL-17/WIL-18) substitui as consultas vazias abaixo por imóveis
 * publicados, mantendo os nomes e formatos das props.
 */
class PropertyController extends Controller
{
    public const PURPOSES = ['venda', 'locacao'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'finalidade' => ['nullable', Rule::in(self::PURPOSES)],
            'cidade' => ['nullable', 'string', 'max:80'],
            'tipo' => ['nullable', 'string', 'max:80'],
        ]);

        $properties = new LengthAwarePaginator([], 0, 12, LengthAwarePaginator::resolveCurrentPage(), [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return Inertia::render('site/properties/Index', [
            'properties' => $properties,
            'filters' => [
                'finalidade' => $filters['finalidade'] ?? null,
                'cidade' => $filters['cidade'] ?? null,
                'tipo' => $filters['tipo'] ?? null,
            ],
            'filter_options' => [
                'cities' => config('site.cities'),
                'types' => [],
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        abort(404);
    }
}
