<?php

namespace App\Http\Resources\Gym;

use App\Models\Gym\GymStudent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student as shown in lists and details. The CPF is always masked here (LGPD minimisation).
 *
 * @mixin GymStudent
 */
class GymStudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->gym_unit_id,
            'name' => $this->name,
            'cpf' => $this->maskedCpf(),
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => $this->birth_date?->toDateString(),
            'is_minor' => $this->isMinor(),
            'status' => $this->status,
            'access_code' => $this->access_code,
            'registration_number' => $this->registration_number,
            'has_access_pin' => $this->access_pin !== null,
            'access_pin_locked' => $this->isAccessPinLocked(),
            'face_status' => $this->face_status,
            'face_enrolled_at' => $this->face_enrolled_at?->toIso8601String(),
            'has_biometric_consent' => $this->when(
                $this->resource->relationLoaded('activeBiometricConsent'),
                fn () => $this->activeBiometricConsent !== null,
            ),
            'has_portal_access' => $this->gym_user_id !== null,
            'guardians' => GymGuardianResource::collection($this->whenLoaded('guardians')),
            'anonymized_at' => $this->anonymized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
