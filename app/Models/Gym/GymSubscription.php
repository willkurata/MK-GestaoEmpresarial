<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Database\Factories\Gym\GymSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Matrícula: recurring contract of a student on a plan. Price and interval are snapshots of the plan.
 *
 * @property string $price
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $next_billing_on
 * @property CarbonImmutable|null $canceled_at
 */
#[Fillable(['gym_id', 'gym_plan_id', 'price', 'billing_interval_months', 'payment_method', 'status', 'starts_on', 'next_billing_on', 'canceled_at', 'cancellation_reason'])]
class GymSubscription extends Model
{
    public const PAYMENT_METHODS = ['pix', 'cartao', 'boleto'];

    /** @use HasFactory<GymSubscriptionFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->status === 'ativa';
    }

    /** @return BelongsTo<GymStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(GymStudent::class, 'gym_student_id');
    }

    /** @return BelongsTo<GymPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(GymPlan::class, 'gym_plan_id');
    }

    /** @return HasMany<GymInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(GymInvoice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_interval_months' => 'integer',
            'starts_on' => 'date',
            'next_billing_on' => 'date',
            'canceled_at' => 'datetime',
        ];
    }
}
