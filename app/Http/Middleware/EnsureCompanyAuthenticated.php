<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user('company');

        if (! $company || $company->status !== 'ativa') {
            if ($company) {
                Auth::guard('company_user')->logout();
                Auth::guard('company')->logout();
                $request->session()->regenerate();
            }

            return redirect()->route('home');
        }

        return $next($request);
    }
}
