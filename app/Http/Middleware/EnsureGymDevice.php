<?php

namespace App\Http\Middleware;

use App\Models\Gym\GymAccessDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a turnstile/edge agent by its bearer token and exposes it as the `gym_device` request attribute.
 */
class EnsureGymDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        $device = $plainToken
            ? GymAccessDevice::query()->with('gym')->where('token_hash', hash('sha256', $plainToken))->first()
            : null;

        if ($device === null || $device->status !== 'ativo' || ! $device->gym->isActive()) {
            abort(Response::HTTP_UNAUTHORIZED, 'Dispositivo não autorizado.');
        }

        $device->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('gym_device', $device);

        return $next($request);
    }
}
