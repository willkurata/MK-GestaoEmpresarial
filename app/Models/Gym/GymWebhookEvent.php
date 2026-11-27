<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Received payment-gateway webhooks, unique per provider/event to keep processing idempotent.
 *
 * @property CarbonImmutable|null $processed_at
 */
#[Fillable(['provider', 'event_id', 'type', 'processed_at'])]
class GymWebhookEvent extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
