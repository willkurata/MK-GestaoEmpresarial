<?php

namespace App\Providers;

use App\Actions\Gym\Payments\PaymentGateway;
use App\Actions\Gym\Payments\SandboxPaymentGateway;
use App\Models\Gym\GymApiToken;
use App\Models\Gym\GymUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Wiring of the App Academia API: token guard, payment gateway and rate limits.
 */
class GymServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            return match (config('academia.payments.gateway')) {
                'sandbox' => new SandboxPaymentGateway,
                default => throw new InvalidArgumentException('Gateway de pagamento da academia não suportado.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('gym-token', fn (Request $request): ?GymUser => $this->resolveUserFromToken($request));

        RateLimiter::for('gym-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('gym')).'|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('gym-api', fn (Request $request): Limit => Limit::perMinute(240)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('gym-device', fn (Request $request): Limit => Limit::perMinute(600)->by($request->ip()));
    }

    private function resolveUserFromToken(Request $request): ?GymUser
    {
        $plainToken = $request->bearerToken();

        if ($plainToken === null || $plainToken === '') {
            return null;
        }

        $token = GymApiToken::query()
            ->with('user.gym')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return null;
        }

        $user = $token->user;

        if (! $user->isActive() || ! $user->gym->isActive()) {
            return null;
        }

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('gym_api_token', $token);

        return $user;
    }
}
