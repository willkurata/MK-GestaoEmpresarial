<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only record of sensitive actions. Never store passwords or secrets in metadata.
 *
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['company_id', 'actor_type', 'actor_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $action,
        ?Model $actor,
        ?int $companyId = null,
        ?Model $subject = null,
        array $metadata = [],
    ): self {
        return self::query()->create([
            'company_id' => $companyId,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->getAttribute('name'),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => request()->ip(),
        ]);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
