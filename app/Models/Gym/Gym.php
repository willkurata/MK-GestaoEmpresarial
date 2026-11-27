<?php

namespace App\Models\Gym;

use Database\Factories\Gym\GymFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Academia (tenant). Every gym record is isolated by `gym_id`.
 */
#[Fillable(['name', 'slug', 'cnpj', 'status'])]
class Gym extends Model
{
    /** @use HasFactory<GymFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->status === 'ativa';
    }

    /** @return HasMany<GymUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(GymUnit::class);
    }

    /** @return HasMany<GymUser, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(GymUser::class);
    }

    /** @return HasMany<GymPlan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(GymPlan::class);
    }

    /** @return HasMany<GymStudent, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(GymStudent::class);
    }

    /** @return HasMany<GymClass, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(GymClass::class);
    }

    /** @return HasMany<GymSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(GymSubscription::class);
    }

    /** @return HasMany<GymInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(GymInvoice::class);
    }

    /** @return HasMany<GymPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(GymPayment::class);
    }

    /** @return HasMany<GymAccessDevice, $this> */
    public function accessDevices(): HasMany
    {
        return $this->hasMany(GymAccessDevice::class);
    }

    /** @return HasMany<GymAccessEvent, $this> */
    public function accessEvents(): HasMany
    {
        return $this->hasMany(GymAccessEvent::class);
    }

    /** @return HasMany<GymAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(GymAuditLog::class);
    }
}
