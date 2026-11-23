<?php

namespace App\Models\CyberCoffe;

use Database\Factories\CyberCoffe\AttendantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Table('cc_attendants')]
#[Fillable(['store_id', 'name', 'username', 'password', 'role', 'status'])]
#[Hidden(['password'])]
class Attendant extends Model
{
    /** @use HasFactory<AttendantFactory> */
    use HasFactory;

    public const array ROLES = ['gerente', 'atendente'];

    public function isManager(): bool
    {
        return $this->role === 'gerente';
    }

    public function isActive(): bool
    {
        return $this->status === 'ativo';
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return MorphMany<ApiToken, $this> */
    public function apiTokens(): MorphMany
    {
        return $this->morphMany(ApiToken::class, 'tokenable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
