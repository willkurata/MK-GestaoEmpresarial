<?php

namespace App\Http\Middleware;

use App\Models\CyberCoffe\Attendant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCyberCoffeManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $attendant = $request->attributes->get('cybercoffe.user');

        if (! $attendant instanceof Attendant || ! $attendant->isManager()) {
            return response()->json(['message' => 'Apenas gerentes podem realizar esta ação.'], 403);
        }

        return $next($request);
    }
}
