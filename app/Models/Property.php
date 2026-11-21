<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * @property string|null $reference_code
 * @property CarbonInterface|null $published_at
 * @property list<string>|null $features
 */
#[Fillable([
    'title', 'purpose', 'type', 'city', 'neighborhood', 'price', 'description',
    'bedrooms', 'bathrooms', 'parking_spaces', 'area', 'features',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'rascunho';

    public const STATUS_PUBLISHED = 'publicado';

    public const STATUS_SOLD = 'vendido';

    public const STATUS_RENTED = 'alugado';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_SOLD, self::STATUS_RENTED];

    /** Statuses visible on the public site. */
    public const PUBLIC_STATUSES = [self::STATUS_PUBLISHED, self::STATUS_SOLD, self::STATUS_RENTED];

    public const PURPOSES = ['venda', 'aluguel'];

    public const TYPES = ['casa', 'apartamento', 'terreno', 'comercial', 'rural', 'outro'];

    public const MAX_PHOTOS = 20;

    public const MAX_FEATURES = 30;

    /** Prefix of the human-friendly reference code (e.g. "MK-00012"). */
    public const REFERENCE_PREFIX = 'MK-';

    /** Name of the public site route that shows a single property, when the site is installed. */
    public const PUBLIC_ROUTE = 'site.properties.show';

    /** Fields that must be filled before a property leaves the draft state. */
    public const LISTING_REQUIRED_FIELDS = ['purpose', 'type', 'city', 'neighborhood', 'price', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    /**
     * Assigns the immutable reference code as soon as the id is known.
     */
    protected static function booted(): void
    {
        static::created(function (Property $property): void {
            if ($property->reference_code === null) {
                $property->forceFill(['reference_code' => self::referenceCodeFor($property->getKey())])->saveQuietly();
            }
        });
    }

    public static function referenceCodeFor(int $id): string
    {
        return self::REFERENCE_PREFIX.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Normalizes what a person types when looking up a code ("mk 12", "MK-00012", "12").
     */
    public static function normalizeReferenceCode(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input);

        if ($digits === '' || $digits === null || strlen($digits) > 12) {
            return null;
        }

        return self::referenceCodeFor((int) $digits);
    }

    /**
     * Address of the property page on the public site, or null while the site
     * is not installed or the property is still a draft.
     */
    public function publicUrl(): ?string
    {
        if ($this->isDraft() || ! Route::has(self::PUBLIC_ROUTE)) {
            return null;
        }

        return route(self::PUBLIC_ROUTE, ['slug' => $this->slug]);
    }

    /** @return HasMany<PropertyPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<PropertyPhoto, $this> */
    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(PropertyPhoto::class, 'cover_photo_id');
    }

    /**
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::PUBLIC_STATUSES);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Requirements still missing before the property can be shown publicly.
     *
     * @return list<string>
     */
    public function missingListingRequirements(): array
    {
        $missing = array_values(array_filter(
            self::LISTING_REQUIRED_FIELDS,
            fn (string $field): bool => blank($this->getAttribute($field)),
        ));

        if (! $this->photos()->exists()) {
            $missing[] = 'photos';
        }

        return $missing;
    }

    /**
     * Statuses this property may move to, given its purpose.
     *
     * @return list<string>
     */
    public function allowedStatuses(): array
    {
        return array_values(array_filter(self::STATUSES, fn (string $status): bool => match ($status) {
            self::STATUS_SOLD => $this->purpose === 'venda',
            self::STATUS_RENTED => $this->purpose === 'aluguel',
            default => true,
        }));
    }

    public function refreshSlug(): void
    {
        $base = Str::slug($this->title) ?: 'imovel';

        $this->slug = Str::limit($base, 180, '').'-'.$this->getKey();
    }

    /**
     * Makes sure the cover points to an existing photo, falling back to the first one.
     */
    public function syncCoverPhoto(): void
    {
        if ($this->cover_photo_id !== null && $this->photos()->whereKey($this->cover_photo_id)->exists()) {
            return;
        }

        $this->cover_photo_id = $this->photos()->value('id');
        $this->save();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'area' => 'decimal:2',
            'features' => 'array',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'parking_spaces' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
