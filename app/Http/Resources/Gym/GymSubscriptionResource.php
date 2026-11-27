<?php

namespace App\Http\Resources\Gym;

use App\Models\Gym\GymSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GymSubscription
 */
class GymSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->gym_student_id,
            'plan_id' => $this->gym_plan_id,
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan->name),
            'price' => $this->price,
            'billing_interval_months' => $this->billing_interval_months,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'starts_on' => $this->starts_on->toDateString(),
            'next_billing_on' => $this->next_billing_on->toDateString(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
        ];
    }
}
