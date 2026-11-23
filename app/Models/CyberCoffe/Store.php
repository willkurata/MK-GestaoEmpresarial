<?php

namespace App\Models\CyberCoffe;

use Database\Factories\CyberCoffe\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('cc_stores')]
#[Fillable(['code', 'name', 'status'])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->status === 'ativa';
    }

    /** @return HasMany<Attendant, $this> */
    public function attendants(): HasMany
    {
        return $this->hasMany(Attendant::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Station, $this> */
    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    /** @return HasMany<Plan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /** @return HasMany<StationSession, $this> */
    public function stationSessions(): HasMany
    {
        return $this->hasMany(StationSession::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<PrintJob, $this> */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
