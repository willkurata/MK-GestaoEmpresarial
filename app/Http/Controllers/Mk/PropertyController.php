<?php

namespace App\Http\Controllers\Mk;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    private const REQUIREMENT_LABELS = [
        'purpose' => 'finalidade',
        'type' => 'tipo',
        'city' => 'cidade',
        'neighborhood' => 'bairro',
        'price' => 'preço',
        'description' => 'descrição',
        'photos' => 'ao menos uma foto',
    ];

    private const STATUS_MESSAGES = [
        Property::STATUS_DRAFT => 'Imóvel voltou para rascunho e saiu do site.',
        Property::STATUS_PUBLISHED => 'Imóvel publicado no site.',
        Property::STATUS_SOLD => 'Imóvel marcado como vendido.',
        Property::STATUS_RENTED => 'Imóvel marcado como alugado.',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Property::STATUSES)],
            'search' => ['nullable', 'string', 'max:160'],
        ]);

        $properties = Property::query()
            ->with('coverPhoto')
            ->withCount('photos')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Property $property): array => [
                ...$property->only([
                    'id', 'title', 'slug', 'status', 'purpose', 'type', 'city', 'neighborhood',
                    'price', 'photos_count', 'published_at', 'updated_at',
                ]),
                'cover_photo_url' => $property->coverPhoto?->url(),
            ]);

        return Inertia::render('mk/Properties/Index', [
            'properties' => $properties,
            'filters' => [
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('mk/Properties/Create', [
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateContent($request, requireListingFields: false);

        $property = DB::transaction(function () use ($validated): Property {
            $property = new Property($validated);
            $property->slug = (string) Str::uuid();
            $property->save();
            $property->refreshSlug();
            $property->save();

            return $property;
        });

        return redirect()->route('mk.properties.edit', $property)
            ->with('success', 'Rascunho do imóvel criado. Adicione as fotos e publique quando estiver pronto.');
    }

    public function edit(Property $property): Response
    {
        $property->load('photos');

        return Inertia::render('mk/Properties/Edit', [
            'property' => [
                ...$property->only([
                    'id', 'title', 'slug', 'status', 'purpose', 'type', 'city', 'neighborhood', 'price',
                    'description', 'bedrooms', 'bathrooms', 'parking_spaces', 'area', 'cover_photo_id',
                    'published_at', 'created_at', 'updated_at',
                ]),
                'features' => $property->features ?? [],
                'photos' => $property->photos->map(fn (PropertyPhoto $photo): array => $photo->toListingArray())->all(),
                'missing_requirements' => $property->missingListingRequirements(),
                'allowed_statuses' => $property->allowedStatuses(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $validated = $this->validateContent($request, requireListingFields: ! $property->isDraft());

        $this->ensurePurposeMatchesStatus($property->status, $validated['purpose'] ?? null, 'purpose');

        $property->fill($validated);
        if ($property->isDraft() && $property->isDirty('title')) {
            $property->refreshSlug();
        }
        $property->save();

        return back()->with('success', 'Imóvel atualizado.');
    }

    public function updateStatus(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Property::STATUSES)],
        ]);
        $status = $validated['status'];

        $this->ensurePurposeMatchesStatus($status, $property->purpose, 'status');

        if ($status !== Property::STATUS_DRAFT) {
            $missing = $property->missingListingRequirements();
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'status' => 'Preencha antes de publicar: '.implode(', ', array_map(
                        fn (string $field): string => self::REQUIREMENT_LABELS[$field] ?? $field,
                        $missing,
                    )).'.',
                ]);
            }
        }

        $property->status = $status;
        if ($status !== Property::STATUS_DRAFT && $property->published_at === null) {
            $property->published_at = now();
        }
        $property->save();
        $property->syncCoverPhoto();

        return back()->with('success', self::STATUS_MESSAGES[$status]);
    }

    public function destroy(Property $property): RedirectResponse
    {
        if (! $property->isDraft()) {
            throw ValidationException::withMessages([
                'property' => 'Somente rascunhos podem ser excluídos. Volte o imóvel para rascunho antes de excluir.',
            ]);
        }

        $photos = $property->photos()->get(['disk', 'path']);

        DB::transaction(function () use ($property): void {
            $property->forceFill(['cover_photo_id' => null])->save();
            $property->delete();
        });

        $photos->each(fn (PropertyPhoto $photo) => Storage::disk($photo->disk)->delete($photo->path));

        return redirect()->route('mk.properties.index')->with('success', 'Rascunho excluído.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateContent(Request $request, bool $requireListingFields): array
    {
        $features = $request->input('features', []);
        $request->merge([
            'features' => is_array($features)
                ? collect($features)
                    ->map(fn ($feature) => is_string($feature) ? trim($feature) : $feature)
                    ->filter(fn ($feature): bool => $feature !== '' && $feature !== null)
                    ->unique(fn ($feature) => is_string($feature) ? mb_strtolower($feature) : $feature)
                    ->values()
                    ->all()
                : $features,
        ]);

        $listing = $requireListingFields ? 'required' : 'nullable';

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'purpose' => [$listing, Rule::in(Property::PURPOSES)],
            'type' => [$listing, Rule::in(Property::TYPES)],
            'city' => [$listing, 'string', 'max:120'],
            'neighborhood' => [$listing, 'string', 'max:120'],
            'price' => [$listing, 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'description' => [$listing, 'string', 'max:10000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:999'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:999'],
            'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:999'],
            'area' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'features' => ['array', 'max:'.Property::MAX_FEATURES],
            'features.*' => ['string', 'max:60'],
        ]);

        foreach (['title', 'city', 'neighborhood', 'description'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim($validated[$field]);
            }
        }

        return $validated;
    }

    private function ensurePurposeMatchesStatus(string $status, ?string $purpose, string $errorKey): void
    {
        $message = match (true) {
            $status === Property::STATUS_SOLD && $purpose !== 'venda' => 'Somente imóveis com finalidade venda podem ficar como vendidos.',
            $status === Property::STATUS_RENTED && $purpose !== 'aluguel' => 'Somente imóveis com finalidade aluguel podem ficar como alugados.',
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages([$errorKey => $message]);
        }
    }

    /**
     * @return array{statuses: list<string>, purposes: list<string>, types: list<string>, max_photos: int, max_features: int}
     */
    private function options(): array
    {
        return [
            'statuses' => Property::STATUSES,
            'purposes' => Property::PURPOSES,
            'types' => Property::TYPES,
            'max_photos' => Property::MAX_PHOTOS,
            'max_features' => Property::MAX_FEATURES,
        ];
    }
}
