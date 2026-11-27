<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Database\Factories\Gym\GymInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mensalidade (one billing period of a subscription). "Vencida" is derived: pending with due date in the past.
 *
 * @property string $amount
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property CarbonImmutable $due_on
 * @property array<string, string>|null $payment_instructions
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $canceled_at
 */
#[Fillable(['gym_id', 'gym_student_id', 'amount', 'period_start', 'period_end', 'due_on', 'status', 'payment_method', 'gateway_reference', 'payment_instructions', 'paid_at', 'canceled_at', 'cancellation_reason'])]
class GymInvoice extends Model
{
    public const STATUS_PENDING = 'pendente';

    public const STATUS_PAID = 'paga';

    public const STATUS_CANCELED = 'cancelada';

    /** @use HasFactory<GymInvoiceFactory> */
    use HasFactory;

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->due_on->lt(today());
    }

    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'vencida' : $this->status;
    }

    /**
     * @param  Builder<GymInvoice>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING)->whereDate('due_on', '<', today());
    }

    /** @return BelongsTo<GymSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(GymSubscription::class, 'gym_subscription_id');
    }

    /** @return BelongsTo<GymStudent, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(GymStudent::class, 'gym_student_id');
    }

    /** @return HasMany<GymPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(GymPayment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'due_on' => 'date',
            'payment_instructions' => 'array',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
