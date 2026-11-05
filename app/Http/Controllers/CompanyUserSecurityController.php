<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CompanyUserSecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('company/Security', [
            'mustChangePassword' => $request->user('company_user')->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password:company_user'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        $request->user('company_user')->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return back()->with('success', 'Senha atualizada com sucesso.');
    }
}
