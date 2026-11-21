<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PublicPropertyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'purpose' => ['nullable', Rule::in(Property::PURPOSES)],
            'type' => ['nullable', Rule::in(Property::TYPES)],
            'status' => ['nullable', Rule::in(Property::PUBLIC_STATUSES)],
            'city' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $properties = Property::query()
            ->publiclyVisible()
            ->with('coverPhoto')
            ->when($filters['purpose'] ?? null, fn ($query, string $value) => $query->where('purpose', $value))
            ->when($filters['type'] ?? null, fn ($query, string $value) => $query->where('type', $value))
            ->when($filters['status'] ?? null, fn ($query, string $value) => $query->where('status', $value))
            ->when($filters['city'] ?? null, fn ($query, string $value) => $query->where('city', $value))
            ->when($filters['neighborhood'] ?? null, fn ($query, string $value) => $query->where('neighborhood', $value))
            ->when($filters['reference'] ?? null, fn ($query, string $value) => $query->where(
                'reference_code',
                Property::normalizeReferenceCode($value) ?? $value,
            ))
            ->when(isset($filters['min_price']), fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('price', '<=', $filters['max_price']))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Property::STATUS_PUBLISHED])
            ->latest('published_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();

        return PropertyResource::collection($properties);
    }

    public function show(string $slug): PropertyResource
    {
        $property = Property::query()
            ->publiclyVisible()
            ->with(['coverPhoto', 'photos'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new PropertyResource($property);
    }
}
