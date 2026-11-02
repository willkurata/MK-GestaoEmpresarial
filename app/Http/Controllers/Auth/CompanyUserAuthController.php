<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CompanyUserAuthController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $company = $request->user('company');
        $username = trim($validated['username']);
        Auth::guard('company_user')->logout();

        $authenticated = Auth::guard('company_user')->attempt([
            'company_id' => $company->getAuthIdentifier(),
            'username' => $username,
            'password' => $validated['password'],
            'status' => 'ativa',
        ], $request->boolean('remember'));

        if (! $authenticated) {
            throw ValidationException::withMessages(['username' => 'Usuário ou senha inválidos.']);
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('company_user')->logout();
        $request->session()->regenerate();

        return redirect()->route('company.portal');
    }
}
