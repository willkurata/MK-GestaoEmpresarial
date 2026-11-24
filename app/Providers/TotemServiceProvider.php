<?php

namespace App\Providers;

use App\Actions\Totem\Pix\PixGateway;
use App\Actions\Totem\Pix\SandboxPixGateway;
use App\Models\Totem;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Wiring for the self-service print totem module (WIL-46).
 */
class TotemServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PixGateway::class, fn (): PixGateway => match (config('totem.pix.driver')) {
            'sandbox' => new SandboxPixGateway(config('totem.pix.webhook_secret')),
            default => throw new InvalidArgumentException('Driver Pix do totem desconhecido: '.config('totem.pix.driver')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('totem-token', fn (Request $request): ?Totem => Totem::findActiveByApiToken($request->bearerToken()));

        RateLimiter::for('totem-display', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('totem-customer', fn (Request $request) => Limit::perMinute(90)->by($request->ip()));
        RateLimiter::for('totem-upload', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('totem-webhook', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('totem-pair', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('totem-agent', fn (Request $request) => Limit::perMinute(240)->by('totem:'.$request->user('totem')?->getAuthIdentifier()));
    }
}
