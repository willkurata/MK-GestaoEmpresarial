<?php

namespace App\Actions\Totem\Pix;

use App\Models\PrintOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Development/test gateway: simulates a PSP until a real one is chosen.
 *
 * A "payment" is a webhook signed with TOTEM_PIX_WEBHOOK_SECRET:
 * header X-Signature = hex HMAC-SHA256 of the raw body,
 * body {"external_id": "...", "status": "paid"}.
 * It refuses to run in production so orders can never be printed for free.
 */
class SandboxPixGateway implements PixGateway
{
    public function __construct(private ?string $webhookSecret)
    {
        if (app()->isProduction()) {
            throw new RuntimeException('O gateway Pix sandbox não pode ser usado em produção. Configure um PSP real.');
        }
    }

    public function name(): string
    {
        return 'sandbox';
    }

    public function createCharge(PrintOrder $order, string $amount, CarbonImmutable $expiresAt): PixCharge
    {
        $externalId = 'sbx_'.Str::lower((string) Str::ulid());

        return new PixCharge($externalId, "SANDBOX-PIX|{$externalId}|{$amount}");
    }

    public function paidChargeFromWebhook(Request $request): ?string
    {
        if ($this->webhookSecret === null || $this->webhookSecret === '') {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $this->webhookSecret);

        if (! hash_equals($expectedSignature, (string) $request->header('X-Signature'))) {
            return null;
        }

        if ($request->json('status') !== 'paid' || ! is_string($request->json('external_id'))) {
            return null;
        }

        return $request->json('external_id');
    }

    public function confirmPaid(string $externalId): bool
    {
        return str_starts_with($externalId, 'sbx_');
    }

    public function refund(string $externalId, string $amount): void
    {
        //
    }
}
