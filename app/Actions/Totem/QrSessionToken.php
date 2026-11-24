<?php

namespace App\Actions\Totem;

use App\Models\Totem;
use Carbon\CarbonImmutable;

/**
 * Stateless rotating token printed in the totem's QR code.
 *
 * The token is an HMAC of the totem code and the current time window, so it
 * needs no storage and scales to any number of totems. A token is accepted
 * during its own window and the next one, giving customers a grace period
 * when they scan right before the code rotates.
 */
class QrSessionToken
{
    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function issue(Totem $totem): array
    {
        $window = $this->currentWindow();

        return [
            'token' => $window.'.'.$this->signature($totem, $window),
            'expires_at' => CarbonImmutable::createFromTimestamp(($window + 1) * $this->windowSeconds()),
        ];
    }

    public function isValid(Totem $totem, string $token): bool
    {
        if (! preg_match('/^(\d+)\.([a-f0-9]{32})$/', $token, $matches)) {
            return false;
        }

        $window = (int) $matches[1];
        $currentWindow = $this->currentWindow();

        if ($window !== $currentWindow && $window !== $currentWindow - 1) {
            return false;
        }

        return hash_equals($this->signature($totem, $window), $matches[2]);
    }

    public function customerUrl(Totem $totem, string $token): string
    {
        return rtrim((string) config('totem.customer_url'), '/').'/'.$totem->code.'?t='.urlencode($token);
    }

    private function currentWindow(): int
    {
        return intdiv(now()->getTimestamp(), $this->windowSeconds());
    }

    private function windowSeconds(): int
    {
        return (int) config('totem.qr_window_seconds');
    }

    private function signature(Totem $totem, int $window): string
    {
        return substr(hash_hmac('sha256', "totem-qr|{$totem->id}|{$totem->code}|{$window}", (string) config('app.key')), 0, 32);
    }
}
