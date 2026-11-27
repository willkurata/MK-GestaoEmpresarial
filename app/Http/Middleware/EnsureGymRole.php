<?php

namespace App\Http\Middleware;

use App\Models\Gym\GymUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts an App Academia route to the given roles (aluno, gestor, administrador).
 */
class EnsureGymRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user('gym');

        if (! $user instanceof GymUser || ! in_array($user->role, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Perfil sem permissão para esta ação.');
        }

        return $next($request);
    }
}
