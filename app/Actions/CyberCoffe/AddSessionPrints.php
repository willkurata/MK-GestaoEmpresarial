<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Product;
use App\Models\CyberCoffe\SessionCharge;
use App\Models\CyberCoffe\StationSession;
use Illuminate\Validation\ValidationException;

class AddSessionPrints
{
    public function __construct(private AddSessionItem $addSessionItem) {}

    /**
     * Charge printed pages to a session at the price per page of an active
     * "impressao" product. When no product is given, the store's only active
     * print price is used; with several, the caller must choose one.
     */
    public function handle(StationSession $session, int $pages, ?int $productId, ?Attendant $attendant, ?string $externalReference = null): SessionCharge
    {
        $prices = $session->store->products()->where('category', 'impressao')->where('active', true);

        if ($productId !== null) {
            $product = $prices->whereKey($productId)->firstOrFail();
        } else {
            $available = $prices->limit(2)->get();

            if ($available->count() !== 1) {
                throw ValidationException::withMessages([
                    'product_id' => $available->isEmpty()
                        ? 'Cadastre um preço de impressão antes de registrar páginas.'
                        : 'Informe o tipo de impressão.',
                ]);
            }

            /** @var Product $product */
            $product = $available->first();
        }

        return $this->addSessionItem->handle($session, $product, [
            'quantity' => $pages,
            'external_reference' => $externalReference,
        ], $attendant);
    }
}
