<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Página inicial do site público.
     *
     * Contrato com o backend de imóveis (WIL-17/WIL-18): `featured_properties`
     * recebe até 6 imóveis publicados no formato PropertySummary
     * (resources/js/types/site.ts). Enquanto o cadastro não existir, a lista
     * fica vazia e a página exibe apenas os serviços.
     */
    public function __invoke(): Response
    {
        return Inertia::render('site/Home', [
            'featured_properties' => [],
        ]);
    }
}
