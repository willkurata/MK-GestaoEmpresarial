<?php

namespace App\Http\Resources\Gym;

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GymInvoice
 */
class GymInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->gym_student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student->name),
            'subscription_id' => $this->gym_subscription_id,
            'amount' => $this->amount,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'status' => $this->displayStatus(),
            'payment_method' => $this->payment_method,
            'payment_instructions' => $this->payment_instructions,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (GymPayment $payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'source' => $payment->source,
                'paid_at' => $payment->paid_at->toIso8601String(),
                'refunded_at' => $payment->refunded_at?->toIso8601String(),
                'refund_reason' => $payment->refund_reason,
            ])),
        ];
    }
}
