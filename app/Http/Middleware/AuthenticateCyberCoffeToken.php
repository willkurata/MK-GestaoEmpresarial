<?php

namespace App\Http\Middleware;

use App\Models\CyberCoffe\ApiToken;
use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Station;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates CyberCoffe API requests by bearer token and binds the token's
 * store to the request, so every query can be scoped to that store.
 */
class AuthenticateCyberCoffeToken
{
    /** @var array<string, class-string> */
    private const array TOKENABLE_TYPES = [
        'attendant' => Attendant::class,
        'customer' => Customer::class,
        'station' => Station::class,
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $plainToken = $request->bearerToken();
        $token = $plainToken ? ApiToken::findValid($plainToken) : null;

        if ($token === null || $token->tokenable_type !== self::TOKENABLE_TYPES[$type]) {
            return $this->unauthenticated();
        }

        $tokenable = $token->tokenable;
        $store = $token->store;

        if ($tokenable === null || ! $store->isActive() || (method_exists($tokenable, 'isActive') && ! $tokenable->isActive())) {
            return $this->unauthenticated();
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('cybercoffe.store', $store);
        $request->attributes->set('cybercoffe.token', $token);
        $request->attributes->set('cybercoffe.user', $tokenable);
        $request->setUserResolver(fn () => $tokenable);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json(['message' => 'Não autenticado.'], 401);
    }
}
