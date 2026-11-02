<?php

namespace App\Models;

use Database\Factories\CompanyUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['company_id', 'cpf', 'name', 'username', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class CompanyUser extends Authenticatable
{
    public const ROLES = ['Administrador', 'Moderador', 'Funcionario'];

    /** @use HasFactory<CompanyUserFactory> */
    use HasFactory;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedCashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function canReverseSales(): bool
    {
        return in_array($this->role, ['Administrador', 'Moderador'], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
