<?php

namespace App\Actions\CyberCoffe;

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Product;
use App\Models\CyberCoffe\SessionCharge;
use App\Models\CyberCoffe\StationSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddSessionItem
{
    /**
     * Add a product or printed pages to an active session's bill. The unit
     * price is snapshotted from the catalog unless a manual price is given;
     * the session's amount due grows by quantity × unit price.
     *
     * A repeated external reference (e.g. a print spooler job id sent again by
     * the station agent) returns the existing line instead of charging twice.
     *
     * @param  array{quantity: int, unit_price?: string|null, description?: string|null, external_reference?: string|null}  $data
     */
    public function handle(StationSession $session, ?Product $product, array $data, ?Attendant $attendant): SessionCharge
    {
        $description = $product !== null ? $product->name : trim((string) ($data['description'] ?? ''));

        if ($description === '') {
            throw ValidationException::withMessages(['description' => 'Informe a descrição do item avulso.']);
        }

        if ($product === null && ! isset($data['unit_price'])) {
            throw ValidationException::withMessages(['unit_price' => 'Informe o preço do item avulso.']);
        }

        return DB::transaction(function () use ($session, $product, $description, $data, $attendant): SessionCharge {
            $locked = StationSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['session' => 'Só é possível adicionar itens a uma sessão ativa.']);
            }

            $reference = $data['external_reference'] ?? null;

            if ($reference !== null) {
                $existing = $locked->charges()->where('external_reference', $reference)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $isManualPrice = isset($data['unit_price']);
            $unitPriceInCents = (int) round(((float) ($isManualPrice ? $data['unit_price'] : $product->price)) * 100);
            $amountInCents = $unitPriceInCents * $data['quantity'];

            $charge = $locked->charges()->create([
                'store_id' => $locked->store_id,
                'kind' => $product?->isPrinting() ? 'impressao' : 'produto',
                'product_id' => $product?->id,
                'created_by' => $attendant?->id,
                'description' => $description,
                'minutes' => 0,
                'quantity' => $data['quantity'],
                'unit_price' => number_format($unitPriceInCents / 100, 2, '.', ''),
                'manual_price' => $isManualPrice,
                'external_reference' => $reference,
                'amount' => number_format($amountInCents / 100, 2, '.', ''),
            ]);

            $locked->update(['amount_due' => number_format(($locked->amountDueInCents() + $amountInCents) / 100, 2, '.', '')]);

            return $charge;
        });
    }
}
