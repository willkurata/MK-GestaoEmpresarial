<?php

namespace App\Models;

use App\Exceptions\InvalidPrintOrderTransition;
use App\PrintOrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PrintOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $totem_id
 * @property string $order_token_hash
 * @property string $pickup_code
 * @property PrintOrderStatus $status
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $file_mime
 * @property string|null $file_path
 * @property string|null $file_sha256
 * @property int|null $page_count
 * @property CarbonImmutable|null $file_analyzed_at
 * @property CarbonImmutable|null $file_purge_at
 * @property CarbonImmutable|null $file_purged_at
 * @property string $color_mode
 * @property bool $duplex
 * @property int $pages_per_sheet
 * @property string|null $page_range
 * @property int $copies
 * @property int|null $quote_pages
 * @property int|null $quote_sides
 * @property int|null $quote_sheets
 * @property string|null $quote_per_side
 * @property string|null $quote_total
 * @property int $sheets_done
 * @property int $attempts
 * @property CarbonImmutable|null $claimed_at
 * @property CarbonImmutable|null $lease_expires_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Totem $totem
 * @property-read PrintPayment|null $payment
 */
#[Fillable([
    'totem_id', 'order_token_hash', 'pickup_code', 'status',
    'file_name', 'file_size', 'file_mime', 'file_path', 'file_sha256', 'page_count', 'file_analyzed_at', 'file_purge_at', 'file_purged_at',
    'color_mode', 'duplex', 'pages_per_sheet', 'page_range', 'copies',
    'quote_pages', 'quote_sides', 'quote_sheets', 'quote_per_side', 'quote_total',
    'sheets_done', 'attempts', 'claimed_at', 'lease_expires_at', 'ready_at', 'expires_at', 'failure_reason',
])]
#[Hidden(['order_token_hash', 'file_path'])]
class PrintOrder extends Model
{
    /** @use HasFactory<PrintOrderFactory> */
    use HasFactory;

    use HasUlids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Totem, $this> */
    public function totem(): BelongsTo
    {
        return $this->belongsTo(Totem::class);
    }

    /** @return HasOne<PrintPayment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(PrintPayment::class);
    }

    /** @return HasMany<PrintOrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PrintOrderEvent::class);
    }

    /**
     * Open a new draft order at a totem and return it with its plain customer token.
     *
     * @return array{0: PrintOrder, 1: string}
     */
    public static function openDraft(Totem $totem): array
    {
        $orderToken = Str::random(48);

        $order = self::query()->create([
            'totem_id' => $totem->id,
            'order_token_hash' => Totem::hashSecret($orderToken),
            'pickup_code' => self::availablePickupCode($totem),
            'status' => PrintOrderStatus::Draft,
            'expires_at' => now()->addMinutes((int) config('totem.draft_ttl_minutes')),
        ]);

        $order->recordEvent(null, PrintOrderStatus::Draft, 'customer');

        return [$order, $orderToken];
    }

    /**
     * Short pickup code that is unique among the totem's orders still visible on screen.
     */
    public static function availablePickupCode(Totem $totem): string
    {
        $codesInUse = self::query()
            ->where('totem_id', $totem->id)
            ->where('created_at', '>=', now()->subDay())
            ->pluck('pickup_code')
            ->all();

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $pickupCode = Totem::randomFromAlphabet(3);

            if (! in_array($pickupCode, $codesInUse, true)) {
                return $pickupCode;
            }
        }

        return Totem::randomFromAlphabet(3);
    }

    public function tokenMatches(?string $orderToken): bool
    {
        return $orderToken !== null
            && $orderToken !== ''
            && hash_equals($this->order_token_hash, Totem::hashSecret($orderToken));
    }

    /**
     * Move the order through the state machine and append an audit event.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $details
     */
    public function transitionTo(PrintOrderStatus $next, string $actor, array $attributes = [], array $details = []): void
    {
        $current = $this->status;

        if (! $current->canTransitionTo($next)) {
            throw new InvalidPrintOrderTransition($current, $next);
        }

        $this->forceFill([...$attributes, 'status' => $next])->save();

        $this->recordEvent($current, $next, $actor, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function recordEvent(?PrintOrderStatus $from, PrintOrderStatus $to, string $actor, array $details = []): void
    {
        $this->events()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor' => $actor,
            'details' => $details === [] ? null : $details,
        ]);
    }

    public function isFileConverting(): bool
    {
        return $this->file_path !== null
            && $this->file_analyzed_at === null
            && $this->status === PrintOrderStatus::Draft;
    }

    /**
     * Sheets the printer must feed for the whole order (all copies).
     */
    public function totalSheets(): ?int
    {
        return $this->quote_sheets === null ? null : $this->quote_sheets * $this->copies;
    }

    /**
     * Delete the customer's file from storage (LGPD: files never outlive their purpose).
     */
    public function purgeFile(): void
    {
        if ($this->file_path !== null) {
            Storage::disk(config('totem.disk'))->delete($this->file_path);
        }

        $this->forceFill([
            'file_path' => null,
            'file_purged_at' => now(),
            'file_purge_at' => $this->file_purge_at !== null && $this->file_purge_at->isPast() ? $this->file_purge_at : now(),
        ])->save();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PrintOrderStatus::class,
            'duplex' => 'boolean',
            'file_size' => 'integer',
            'page_count' => 'integer',
            'pages_per_sheet' => 'integer',
            'copies' => 'integer',
            'quote_pages' => 'integer',
            'quote_sides' => 'integer',
            'quote_sheets' => 'integer',
            'quote_per_side' => 'decimal:2',
            'quote_total' => 'decimal:2',
            'sheets_done' => 'integer',
            'attempts' => 'integer',
            'file_analyzed_at' => 'datetime',
            'file_purge_at' => 'datetime',
            'file_purged_at' => 'datetime',
            'claimed_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
