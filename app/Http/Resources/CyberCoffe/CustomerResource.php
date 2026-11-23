<?php

namespace App\Http\Resources\CyberCoffe;

use App\Models\CyberCoffe\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
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
            'name' => $this->name,
            'phone' => $this->phone,
            'has_document' => $this->document !== null,
            'can_login' => $this->password !== null,
            'status' => $this->status,
            'credit_balance' => number_format($this->creditBalanceInCents() / 100, 2, '.', ''),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
