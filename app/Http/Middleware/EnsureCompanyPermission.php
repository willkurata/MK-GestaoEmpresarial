<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $companyUser = $request->user('company_user');

        if (! $companyUser || ! $companyUser->can($ability)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
