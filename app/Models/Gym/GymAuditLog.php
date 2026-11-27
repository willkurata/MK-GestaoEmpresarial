<?php

namespace App\Models\Gym;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Accountability log for sensitive actions (biometrics, personal data, refunds, manual releases).
 *
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['gym_id', 'gym_user_id', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address'])]
class GymAuditLog extends Model
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(Request $request, GymUser $actor, string $action, ?Model $subject = null, array $metadata = []): self
    {
        return self::create([
            'gym_id' => $actor->gym_id,
            'gym_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request->ip(),
        ]);
    }

    /** @return BelongsTo<GymUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(GymUser::class, 'gym_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
