<?php

namespace App\Models\Gym;

use Database\Factories\Gym\GymPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $price
 */
#[Fillable(['name', 'description', 'price', 'billing_interval_months', 'grace_days', 'status'])]
class GymPlan extends Model
{
    public const BILLING_INTERVALS = [1, 3, 6, 12];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ativo', 'grace_days' => 5];

    /** @use HasFactory<GymPlanFactory> */
    use HasFactory;

    /** @return BelongsTo<Gym, $this> */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_interval_months' => 'integer',
            'grace_days' => 'integer',
        ];
    }
}
