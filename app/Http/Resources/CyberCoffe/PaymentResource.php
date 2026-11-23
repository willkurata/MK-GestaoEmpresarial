<?php

namespace App\Http\Resources\CyberCoffe;

use App\Models\CyberCoffe\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purpose' => $this->purpose,
            'method' => $this->method,
            'amount' => $this->amount,
            'amount_received' => $this->amount_received,
            'change_amount' => $this->change_amount,
            'reference' => $this->reference,
            'customer_id' => $this->customer_id,
            'station_session_id' => $this->station_session_id,
            'received_by' => $this->received_by,
            'paid_at' => $this->paid_at->toIso8601String(),
        ];
    }
}
