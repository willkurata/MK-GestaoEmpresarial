<?php

namespace App\Http\Resources\Gym;

use App\Models\Gym\GymGuardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GymGuardian
 */
class GymGuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cpf' => '***.***.***-'.substr((string) $this->cpf, -2),
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
