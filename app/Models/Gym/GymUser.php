<?php

namespace App\Models\Gym;

use Database\Factories\Gym\GymUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * Login account of the gym app: a student (aluno), a manager (gestor) or an administrator.
 */
#[Fillable(['gym_unit_id', 'name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password'])]
class GymUser extends Authenticatable
{
    public const ROLE_STUDENT = 'aluno';

    public const ROLE_MANAGER = 'gestor';

    public const ROLE_ADMINISTRATOR = 'administrador';

    public const ROLES = [self::ROLE_STUDENT, self::ROLE_MANAGER, self::ROLE_ADMINISTRATOR];

    public const STAFF_ROLES = [self::ROLE_MANAGER, self::ROLE_ADMINISTRATOR];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ativo'];

    /** @use HasFactory<GymUserFactory> */
    use HasFactory;

    /** @return BelongsTo<Gym, $this> */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /** @return BelongsTo<GymUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(GymUnit::class, 'gym_unit_id');
    }

    /** @return HasOne<GymStudent, $this> */
    public function student(): HasOne
    {
        return $this->hasOne(GymStudent::class);
    }

    /** @return HasMany<GymApiToken, $this> */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(GymApiToken::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ativo';
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    /**
     * Managers bound to a unit only operate that unit; administrators and network-wide managers see all units.
     */
    public function restrictedUnitId(): ?int
    {
        return $this->role === self::ROLE_MANAGER ? $this->gym_unit_id : null;
    }

    public function canAccessUnit(int $unitId): bool
    {
        $restrictedUnitId = $this->restrictedUnitId();

        return $restrictedUnitId === null || $restrictedUnitId === $unitId;
    }

    /**
     * Students visible to this staff member, always limited to the user's gym.
     *
     * @return Builder<GymStudent>
     */
    public function visibleStudents(): Builder
    {
        return GymStudent::query()
            ->where('gym_id', $this->gym_id)
            ->when($this->restrictedUnitId(), fn (Builder $query, int $unitId) => $query->where('gym_unit_id', $unitId));
    }

    /**
     * Issues a new API token. The plain value is returned once; only its SHA-256 hash is stored.
     */
    public function issueApiToken(string $name): string
    {
        $plainToken = Str::random(64);

        $this->apiTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays((int) config('academia.token_ttl_days')),
        ]);

        return $plainToken;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
