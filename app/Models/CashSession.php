<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $company_id
 * @property int $cash_register_id
 * @property int $company_user_id
 * @property CarbonImmutable $opened_at
 * @property string $opening_amount
 * @property string $status
 * @property string|null $closing_mode
 * @property CarbonImmutable|null $closed_at
 * @property int|null $closed_by_company_user_id
 * @property CarbonImmutable|null $continued_at
 * @property int|null $previous_cash_session_id
 * @property string|null $transferred_amount
 * @property string|null $expected_amount
 * @property string|null $counted_amount
 * @property string|null $difference_amount
 */
#[Fillable(['company_id', 'cash_register_id', 'company_user_id', 'opened_at', 'opening_amount', 'status', 'closing_mode', 'closed_at', 'closed_by_company_user_id', 'continued_at', 'previous_cash_session_id', 'transferred_amount', 'expected_amount', 'counted_amount', 'difference_amount'])]
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    /**
     * Business day boundaries follow the company's local time, not the UTC app clock.
     */
    public const BUSINESS_TIMEZONE = 'America/Sao_Paulo';

    /** Closed by the operator after counting the drawer. */
    public const CLOSING_COUNTED = 'conferido';

    /** Closed by the system on the next day without counting; needs review. */
    public const CLOSING_AUTOMATIC = 'automatico';

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /** @return BelongsTo<CompanyUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'company_user_id');
    }

    /** @return BelongsTo<CompanyUser, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'closed_by_company_user_id');
    }

    /** @return BelongsTo<CashSession, $this> */
    public function previousSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_cash_session_id');
    }

    /** @return HasOne<CashSession, $this> */
    public function nextSession(): HasOne
    {
        return $this->hasOne(self::class, 'previous_cash_session_id');
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Whether the shift is still open although it started on an earlier business day.
     */
    public function isOpenFromPreviousDay(?CarbonInterface $now = null): bool
    {
        return $this->status === 'aberto'
            && $this->businessDate($this->opened_at) < $this->businessDate($now ?? now());
    }

    /**
     * A shift left open from a previous day blocks new operations until someone decides
     * what to do with it. Choosing to keep it only counts for the current business day.
     */
    public function needsCarryOverDecision(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->isOpenFromPreviousDay($now)
            && ($this->continued_at === null || $this->businessDate($this->continued_at) < $this->businessDate($now));
    }

    /**
     * Number of business days since the shift was opened.
     */
    public function daysOpen(?CarbonInterface $until = null): int
    {
        $openedOn = $this->opened_at->copy()->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay();
        $until = ($until ?? $this->closed_at ?? now())->copy()->setTimezone(self::BUSINESS_TIMEZONE)->startOfDay();

        return (int) $openedOn->diffInDays($until);
    }

    /**
     * Physical cash expected in the drawer, in cents: opening amount plus cash movements
     * (cash sales minus withdrawals and cash refunds). Pix never changes physical cash.
     */
    public function expectedCashCents(): int
    {
        return self::toCents((string) $this->opening_amount)
            + self::toCents((string) $this->movements()->where('method', 'Dinheiro')->sum('amount'));
    }

    /**
     * Pix received in the shift (net of Pix refunds), in cents.
     */
    public function pixTotalCents(): int
    {
        return self::toCents((string) $this->movements()->where('method', 'Pix')->sum('amount'));
    }

    public static function toCents(string $amount): int
    {
        $isNegative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $whole = ltrim($whole, '-');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $isNegative ? -$cents : $cents;
    }

    public static function fromCents(int $amount): string
    {
        return sprintf('%s%d.%02d', $amount < 0 ? '-' : '', intdiv(abs($amount), 100), abs($amount) % 100);
    }

    private function businessDate(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone(self::BUSINESS_TIMEZONE)->toDateString();
    }

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'continued_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'transferred_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
        ];
    }
}
