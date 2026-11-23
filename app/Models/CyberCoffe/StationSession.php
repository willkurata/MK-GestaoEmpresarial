<?php

namespace App\Models\CyberCoffe;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CyberCoffe\StationSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One usage of a station, from check-in to check-out. The server clock is the
 * source of truth for elapsed and remaining time.
 *
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $closed_at
 */
#[Table('cc_station_sessions')]
#[Fillable(['store_id', 'station_id', 'customer_id', 'opened_by', 'closed_by', 'billing', 'hourly_rate', 'started_at', 'ends_at', 'closed_at', 'amount_due', 'status'])]
class StationSession extends Model
{
    /** @use HasFactory<StationSessionFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->status === 'ativa';
    }

    public function isPackage(): bool
    {
        return $this->billing === 'pacote';
    }

    public function elapsedSeconds(?CarbonInterface $at = null): int
    {
        $end = $this->closed_at ?? $at ?? now();

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }

    /**
     * Remaining prepaid seconds, or null for open ("avulso") sessions.
     */
    public function remainingSeconds(?CarbonInterface $at = null): ?int
    {
        if (! $this->isPackage() || $this->ends_at === null) {
            return null;
        }

        if (! $this->isActive()) {
            return 0;
        }

        return max(0, (int) ceil(($at ?? now())->diffInSeconds($this->ends_at, false)));
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        return $this->isActive() && $this->isPackage() && $this->remainingSeconds($at) === 0;
    }

    /**
     * Minutes billed for an open session: every started minute counts, minimum one.
     */
    public function billableMinutes(?CarbonInterface $at = null): int
    {
        return max(1, (int) ceil($this->elapsedSeconds($at) / 60));
    }

    /**
     * Current amount for an open session if it were closed now, in cents.
     */
    public function openAmountInCents(?CarbonInterface $at = null): int
    {
        $rateInCents = (int) round(((float) $this->hourly_rate) * 100);

        return (int) round($this->billableMinutes($at) * $rateInCents / 60);
    }

    public function amountDueInCents(): int
    {
        return (int) round(((float) $this->amount_due) * 100);
    }

    public function amountPaidInCents(): int
    {
        return (int) round(((float) $this->payments()->sum('amount')) * 100);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Station, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Attendant, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Attendant::class, 'opened_by');
    }

    /** @return BelongsTo<Attendant, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Attendant::class, 'closed_by');
    }

    /** @return HasMany<SessionCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(SessionCharge::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
