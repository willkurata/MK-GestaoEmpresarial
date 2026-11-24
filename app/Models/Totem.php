<?php

namespace App\Models;

use App\TotemStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TotemFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $location
 * @property string|null $help_contact
 * @property TotemStatus $status
 * @property string|null $api_token_hash
 * @property string|null $pairing_code_hash
 * @property CarbonImmutable|null $pairing_code_expires_at
 * @property CarbonImmutable|null $paired_at
 * @property CarbonImmutable|null $last_seen_at
 * @property string|null $agent_version
 * @property string $printer_state
 * @property list<string>|null $printer_reasons
 * @property int|null $paper_level
 * @property list<array{color: string, level: int|null}>|null $printer_markers
 * @property bool $supports_color
 * @property bool $supports_duplex
 * @property string $price_bw_per_side
 * @property string|null $price_color_per_side
 * @property int $max_copies
 * @property-read int $queued_orders_count
 * @property-read int $printing_orders_count
 * @property-read int $failed_orders_count
 */
#[Fillable([
    'code', 'name', 'location', 'help_contact', 'status', 'supports_color', 'supports_duplex',
    'price_bw_per_side', 'price_color_per_side', 'max_copies',
])]
#[Hidden(['api_token_hash', 'pairing_code_hash'])]
class Totem extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    /** @use HasFactory<TotemFactory> */
    use HasFactory;

    /** Alphabet without look-alike characters (O/0, I/1). */
    public const string CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * Device tokens replace "remember me"; there is no remember token column.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /** @return HasMany<PrintOrder, $this> */
    public function printOrders(): HasMany
    {
        return $this->hasMany(PrintOrder::class);
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'T'.self::randomFromAlphabet(6);
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    public static function randomFromAlphabet(int $length): string
    {
        $characters = '';

        for ($index = 0; $index < $length; $index++) {
            $characters .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $characters;
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public static function findActiveByApiToken(?string $token): ?self
    {
        if ($token === null || $token === '') {
            return null;
        }

        return self::query()
            ->where('api_token_hash', self::hashSecret($token))
            ->where('status', TotemStatus::Active)
            ->first();
    }

    /**
     * Issue a new device token, invalidating any previous one. Only the hash is stored.
     */
    public function issueApiToken(): string
    {
        $token = Str::random(64);

        $this->forceFill(['api_token_hash' => self::hashSecret($token)])->save();

        return $token;
    }

    /**
     * Create a short-lived pairing code the totem agent exchanges for its device token.
     */
    public function issuePairingCode(): string
    {
        $pairingCode = self::randomFromAlphabet(8);

        $this->forceFill([
            'pairing_code_hash' => self::hashSecret($pairingCode),
            'pairing_code_expires_at' => now()->addMinutes((int) config('totem.pairing_code_ttl_minutes')),
        ])->save();

        return $pairingCode;
    }

    public function pairingCodeMatches(string $pairingCode): bool
    {
        return $this->pairing_code_hash !== null
            && $this->pairing_code_expires_at?->isFuture()
            && hash_equals($this->pairing_code_hash, self::hashSecret(strtoupper($pairingCode)));
    }

    public function isActive(): bool
    {
        return $this->status === TotemStatus::Active;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds((int) config('totem.offline_after_seconds')));
    }

    /**
     * The printer state shown to customers; a silent agent always means "offline".
     */
    public function displayPrinterState(): string
    {
        return $this->isOnline() ? $this->printer_state : 'offline';
    }

    public function pricePerSide(string $colorMode): ?string
    {
        return $colorMode === 'color' ? $this->price_color_per_side : $this->price_bw_per_side;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TotemStatus::class,
            'pairing_code_expires_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'printer_reasons' => 'array',
            'printer_markers' => 'array',
            'supports_color' => 'boolean',
            'supports_duplex' => 'boolean',
            'price_bw_per_side' => 'decimal:2',
            'price_color_per_side' => 'decimal:2',
            'max_copies' => 'integer',
        ];
    }
}
