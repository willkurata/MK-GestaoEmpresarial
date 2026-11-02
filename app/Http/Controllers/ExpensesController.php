<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpensesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Company $company */
        $company = $request->user('company');

        return Inertia::render('Expenses/Index', [
            'expenses' => $company->expenses()
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'type' => ['required', Rule::in(['fixa', 'variavel'])],
            'expense_date' => ['required', 'date'],
        ]);

        $company->expenses()->create([
            ...$validated,
            'description' => trim($validated['description']),
        ]);

        return back()->with('success', 'Despesa registrada.');
    }
}
