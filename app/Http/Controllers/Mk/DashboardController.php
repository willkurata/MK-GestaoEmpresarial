<?php

namespace App\Http\Controllers\Mk;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('mk/Dashboard', [
            'companies' => Company::query()
                ->select(['id', 'cnpj', 'name', 'status', 'created_at'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
