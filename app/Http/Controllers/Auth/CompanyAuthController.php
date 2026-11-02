<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAuthController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user('company')) {
            return redirect()->route('company.portal');
        }

        return Inertia::render('auth/CompanyLogin');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cnpj' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
        ]);
        $cnpj = preg_replace('/\D+/', '', $validated['cnpj']);

        if (strlen($cnpj) !== 14) {
            throw ValidationException::withMessages(['cnpj' => 'Informe um CNPJ válido.']);
        }

        Auth::guard('company_user')->logout();
        $authenticated = Auth::guard('company')->attempt([
            'cnpj' => $cnpj,
            'password' => $validated['password'],
            'status' => 'ativa',
        ], $request->boolean('remember'));

        if (! $authenticated) {
            throw ValidationException::withMessages(['cnpj' => 'CNPJ ou senha inválidos.']);
        }

        $request->session()->regenerate();

        return redirect()->route('company.portal');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('company_user')->logout();
        Auth::guard('company')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
