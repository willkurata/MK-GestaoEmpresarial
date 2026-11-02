<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyUserAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user('company');
        $companyUser = $request->user('company_user');

        if (! $company) {
            return redirect()->route('home');
        }

        if (! $companyUser
            || $companyUser->status !== 'ativa'
            || (int) $companyUser->company_id !== (int) $company->getAuthIdentifier()) {
            if ($companyUser) {
                Auth::guard('company_user')->logout();
                $request->session()->regenerate();
            }

            return redirect()->route('company.portal');
        }

        return $next($request);
    }
}
