<?php

namespace App\Http\Resources;

use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a listed property. Never exposes drafts.
 *
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'type' => $this->type,
            'city' => $this->city,
            'neighborhood' => $this->neighborhood,
            'price' => $this->price,
            'description' => $this->description,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'parking_spaces' => $this->parking_spaces,
            'area' => $this->area,
            'features' => $this->features ?? [],
            'cover_photo' => $this->coverPhoto?->toListingArray(),
            'photos' => $this->whenLoaded(
                'photos',
                fn () => $this->photos->map(fn (PropertyPhoto $photo): array => $photo->toListingArray())->all(),
            ),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
