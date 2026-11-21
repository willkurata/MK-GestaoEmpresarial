<?php

namespace App\Models;

use Database\Factories\PropertyPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['disk', 'path', 'position'])]
class PropertyPhoto extends Model
{
    /** @use HasFactory<PropertyPhotoFactory> */
    use HasFactory;

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** @return array{id: int, url: string, position: int} */
    public function toListingArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'position' => $this->position,
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
