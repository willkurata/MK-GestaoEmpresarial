<?php

namespace App\Models;

use Database\Factories\CompanyUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['company_id', 'cpf', 'name', 'username', 'password', 'role', 'status', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'must_change_password'])]
class CompanyUser extends Authenticatable
{
    public const ROLES = ['Administrador', 'Moderador', 'Funcionario'];

    /**
     * Permission matrix for V1 company roles. Abilities absent from this list
     * (dashboard, own cashier turn, own password) are available to every active user.
     *
     * @var array<string, list<string>>
     */
    public const PERMISSIONS = [
        'reverse-sales' => ['Administrador', 'Moderador'],
        'manage-products' => ['Administrador', 'Moderador'],
        'manage-expenses' => ['Administrador', 'Moderador'],
        'view-reports' => ['Administrador', 'Moderador'],
        'manage-users' => ['Administrador'],
        'withdraw-from-any-shift' => ['Administrador', 'Moderador'],
    ];

    /**
     * Session key holding a keyed fingerprint of the password hash used at sign-in.
     */
    public const PASSWORD_FINGERPRINT_SESSION_KEY = 'company_user_password_fingerprint';

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
        return $this->hasPermission('reverse-sales');
    }

    public function hasPermission(string $ability): bool
    {
        return $this->status === 'ativa'
            && in_array($this->role, self::PERMISSIONS[$ability] ?? [], true);
    }

    public function isActiveAdministrator(): bool
    {
        return $this->role === 'Administrador' && $this->status === 'ativa';
    }

    /**
     * Keyed fingerprint of the current password hash, so a session can detect a later
     * password change or reset without storing the hash itself.
     */
    public function passwordFingerprint(): string
    {
        return hash_hmac('sha256', (string) $this->getAuthPassword(), (string) config('app.key'));
    }

    public function isLastActiveAdministrator(): bool
    {
        return $this->isActiveAdministrator()
            && ! self::query()
                ->where('company_id', $this->company_id)
                ->whereKeyNot($this->getKey())
                ->where('role', 'Administrador')
                ->where('status', 'ativa')
                ->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }
}
