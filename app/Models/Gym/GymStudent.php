<?php

namespace App\Models\Gym;

use Carbon\CarbonImmutable;
use Database\Factories\Gym\GymStudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Aluno. The CPF is encrypted at rest; `cpf_hash` (HMAC) enforces uniqueness per gym without exposing it.
 *
 * @property string|null $cpf
 * @property CarbonImmutable|null $birth_date
 * @property CarbonImmutable|null $face_enrolled_at
 * @property CarbonImmutable|null $anonymized_at
 * @property string|null $registration_number
 * @property string|null $access_pin
 * @property int $access_pin_failed_attempts
 * @property CarbonImmutable|null $access_pin_locked_at
 */
#[Fillable(['gym_unit_id', 'name', 'cpf', 'email', 'phone', 'birth_date', 'status'])]
#[Hidden(['cpf', 'cpf_hash', 'access_pin'])]
class GymStudent extends Model
{
    public const FACE_NOT_ENROLLED = 'nao_cadastrada';

    public const FACE_ENROLLED = 'cadastrada';

    /**
     * Wrong PINs in a row before the keypad fallback is locked until staff sets a new PIN.
     */
    public const MAX_PIN_ATTEMPTS = 5;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'ativo',
        'face_status' => self::FACE_NOT_ENROLLED,
    ];

    /** @use HasFactory<GymStudentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (GymStudent $student): void {
            $student->access_code ??= (string) Str::uuid();
            $student->registration_number ??= self::newRegistrationNumber($student->gym_id);
        });

        static::saving(function (GymStudent $student): void {
            if ($student->isDirty('cpf')) {
                $student->cpf_hash = $student->cpf === null ? null : self::hashCpf($student->cpf);
            }
        });
    }

    public static function normalizeCpf(string $cpf): string
    {
        return (string) preg_replace('/\D+/', '', $cpf);
    }

    public static function hashCpf(string $cpf): string
    {
        return hash_hmac('sha256', self::normalizeCpf($cpf), (string) config('app.key'));
    }

    /**
     * Short numeric "matrícula" typed on the turnstile keypad when the face or QR code cannot be read.
     */
    public static function newRegistrationNumber(int $gymId): string
    {
        do {
            $number = (string) random_int(100000, 999999);
        } while (self::query()->where('gym_id', $gymId)->where('registration_number', $number)->exists());

        return $number;
    }

    public function setAccessPin(string $pin): void
    {
        $this->forceFill([
            'access_pin' => Hash::make($pin),
            'access_pin_failed_attempts' => 0,
            'access_pin_locked_at' => null,
        ])->save();
    }

    public function isAccessPinLocked(): bool
    {
        return $this->access_pin_locked_at !== null;
    }

    /**
     * Checks the keypad PIN and locks the fallback after too many wrong attempts in a row.
     *
     * @return 'valida'|'invalida'|'bloqueada'
     */
    public function attemptAccessPin(string $pin): string
    {
        if ($this->isAccessPinLocked()) {
            return 'bloqueada';
        }

        if ($this->access_pin !== null && Hash::check($pin, $this->access_pin)) {
            if ($this->access_pin_failed_attempts > 0) {
                $this->forceFill(['access_pin_failed_attempts' => 0])->save();
            }

            return 'valida';
        }

        $attempts = $this->access_pin_failed_attempts + 1;
        $locked = $attempts >= self::MAX_PIN_ATTEMPTS;
        $this->forceFill([
            'access_pin_failed_attempts' => $attempts,
            'access_pin_locked_at' => $locked ? now() : null,
        ])->save();

        return $locked ? 'bloqueada' : 'invalida';
    }

    public function isActive(): bool
    {
        return $this->status === 'ativo' && $this->anonymized_at === null;
    }

    public function isMinor(): bool
    {
        return $this->birth_date !== null && $this->birth_date->diffInYears(now()) < 18;
    }

    public function maskedCpf(): ?string
    {
        if ($this->cpf === null) {
            return null;
        }

        return '***.***.***-'.substr($this->cpf, -2);
    }

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

    /** @return BelongsTo<GymUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(GymUser::class, 'gym_user_id');
    }

    /** @return HasMany<GymGuardian, $this> */
    public function guardians(): HasMany
    {
        return $this->hasMany(GymGuardian::class);
    }

    /** @return HasMany<GymBiometricConsent, $this> */
    public function biometricConsents(): HasMany
    {
        return $this->hasMany(GymBiometricConsent::class);
    }

    /** @return HasOne<GymBiometricConsent, $this> */
    public function activeBiometricConsent(): HasOne
    {
        return $this->hasOne(GymBiometricConsent::class)->whereNull('revoked_at')->latestOfMany('granted_at');
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

    /** @return HasMany<GymAccessEvent, $this> */
    public function accessEvents(): HasMany
    {
        return $this->hasMany(GymAccessEvent::class);
    }

    /** @return BelongsToMany<GymClass, $this> */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(GymClass::class, 'gym_class_students')
            ->withPivot(['id', 'joined_on', 'left_on'])
            ->withTimestamps();
    }

    /** @return HasMany<GymClassAttendance, $this> */
    public function classAttendances(): HasMany
    {
        return $this->hasMany(GymClassAttendance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cpf' => 'encrypted',
            'birth_date' => 'date',
            'face_enrolled_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'access_pin_failed_attempts' => 'integer',
            'access_pin_locked_at' => 'datetime',
        ];
    }
}
