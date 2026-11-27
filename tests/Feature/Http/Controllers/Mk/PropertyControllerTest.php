<?php

use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
    $this->admin = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function completeListingPayload(array $overrides = []): array
{
    return [
        'title' => 'Casa com quintal no Centro',
        'purpose' => 'venda',
        'type' => 'casa',
        'city' => 'Londrina',
        'neighborhood' => 'Centro',
        'price' => '450000.00',
        'description' => 'Casa térrea com três quartos.',
        'bedrooms' => 3,
        'bathrooms' => 2,
        'parking_spaces' => 1,
        'area' => '120.50',
        'features' => ['Quintal', ' quintal ', 'Churrasqueira', ''],
        ...$overrides,
    ];
}

it('keeps the property panel behind MK authentication', function () {
    auth()->logout();

    $this->get(route('mk.properties.index'))->assertRedirect(route('login'));
    $this->post(route('mk.properties.store'), ['title' => 'Teste'])->assertRedirect(route('login'));
    expect(Property::count())->toBe(0);
});

it('creates a draft with only a title and redirects to the edit page', function () {
    $response = $this->actingAs($this->admin)->post(route('mk.properties.store'), ['title' => '  Apartamento Gleba  ']);

    $property = Property::sole();
    $response->assertSessionHasNoErrors()->assertRedirect(route('mk.properties.edit', $property));
    expect($property->status)->toBe(Property::STATUS_DRAFT)
        ->and($property->title)->toBe('Apartamento Gleba')
        ->and($property->slug)->toBe('apartamento-gleba-'.$property->id)
        ->and($property->published_at)->toBeNull();
});

it('normalizes characteristics when saving', function () {
    $this->actingAs($this->admin)->post(route('mk.properties.store'), completeListingPayload())
        ->assertSessionHasNoErrors();

    expect(Property::sole()->features)->toBe(['Quintal', 'Churrasqueira']);
});

it('exposes the edit contract with photos, missing requirements and allowed statuses', function () {
    $property = Property::factory()->create(['description' => null]);
    $photo = PropertyPhoto::factory()->for($property)->create();

    $this->actingAs($this->admin)->get(route('mk.properties.edit', $property))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mk/Properties/Edit', false)
            ->where('property.id', $property->id)
            ->where('property.photos.0.id', $photo->id)
            ->where('property.missing_requirements', ['description'])
            ->where('property.allowed_statuses', ['rascunho', 'publicado', 'vendido'])
            ->where('options.max_photos', Property::MAX_PHOTOS));
});

it('lists properties filtered by status', function () {
    Property::factory()->create(['title' => 'Rascunho A']);
    Property::factory()->published()->create(['title' => 'Publicado B']);

    $this->actingAs($this->admin)->get(route('mk.properties.index', ['status' => 'publicado']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mk/Properties/Index', false)
            ->has('properties.data', 1)
            ->where('properties.data.0.title', 'Publicado B')
            ->where('properties.data.0.photos_count', 1));
});

it('refuses to publish while required fields or photos are missing', function () {
    $property = Property::factory()->create(['city' => null]);

    $this->actingAs($this->admin)
        ->from(route('mk.properties.edit', $property))
        ->patch(route('mk.properties.status.update', $property), ['status' => 'publicado'])
        ->assertSessionHasErrors(['status' => 'Preencha antes de publicar: cidade, ao menos uma foto.']);

    expect($property->fresh()->status)->toBe(Property::STATUS_DRAFT);
});

it('publishes a complete property, stamping the first publication date and the cover', function () {
    $property = Property::factory()->create();
    $photo = PropertyPhoto::factory()->for($property)->create();

    $this->actingAs($this->admin)
        ->patch(route('mk.properties.status.update', $property), ['status' => 'publicado'])
        ->assertSessionHasNoErrors();

    $property->refresh();
    expect($property->status)->toBe(Property::STATUS_PUBLISHED)
        ->and($property->published_at)->not->toBeNull()
        ->and($property->cover_photo_id)->toBe($photo->id);
});

it('only marks sold for sale and rented for rental properties', function () {
    $forSale = Property::factory()->published()->create();
    $forRent = Property::factory()->forRent()->published()->create();
    $this->actingAs($this->admin);

    $this->patch(route('mk.properties.status.update', $forSale), ['status' => 'alugado'])->assertSessionHasErrors('status');
    $this->patch(route('mk.properties.status.update', $forRent), ['status' => 'vendido'])->assertSessionHasErrors('status');

    $this->patch(route('mk.properties.status.update', $forSale), ['status' => 'vendido'])->assertSessionHasNoErrors();
    $this->patch(route('mk.properties.status.update', $forRent), ['status' => 'alugado'])->assertSessionHasNoErrors();

    expect($forSale->fresh()->status)->toBe('vendido')
        ->and($forRent->fresh()->status)->toBe('alugado');
});

it('requires listing fields when editing a property that is visible on the site', function () {
    $property = Property::factory()->published()->create();

    $this->actingAs($this->admin)
        ->patch(route('mk.properties.update', $property), completeListingPayload(['price' => null]))
        ->assertSessionHasErrors('price');
});

it('keeps the public slug stable after publication', function () {
    $property = Property::factory()->published()->create(['slug' => 'casa-antiga-1']);

    $this->actingAs($this->admin)
        ->patch(route('mk.properties.update', $property), completeListingPayload(['title' => 'Novo título']))
        ->assertSessionHasNoErrors();

    expect($property->fresh())->title->toBe('Novo título')->slug->toBe('casa-antiga-1');
});

it('uploads photos, sets the first one as cover and stores them on the configured disk', function () {
    $property = Property::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('mk.properties.photos.store', $property), [
            'photos' => [UploadedFile::fake()->image('sala.jpg'), UploadedFile::fake()->image('quarto.png')],
        ])
        ->assertSessionHasNoErrors();

    $photos = $property->photos()->get();
    expect($photos)->toHaveCount(2)
        ->and($photos->pluck('position')->all())->toBe([0, 1])
        ->and($property->fresh()->cover_photo_id)->toBe($photos->first()->id);
    $photos->each(fn (PropertyPhoto $photo) => Storage::disk('public')->assertExists($photo->path));
});

it('rejects non-image uploads and uploads beyond the photo limit', function () {
    $property = Property::factory()->create();
    PropertyPhoto::factory()->for($property)->count(Property::MAX_PHOTOS)->create();
    $this->actingAs($this->admin);

    $this->post(route('mk.properties.photos.store', $property), [
        'photos' => [UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf')],
    ])->assertSessionHasErrors('photos.0');

    $this->post(route('mk.properties.photos.store', $property), [
        'photos' => [UploadedFile::fake()->image('extra.jpg')],
    ])->assertSessionHasErrors('photos');

    expect($property->photos()->count())->toBe(Property::MAX_PHOTOS);
});

it('changes the cover only to a photo of the same property', function () {
    $property = Property::factory()->published()->create();
    $otherPhoto = PropertyPhoto::factory()->create();
    $ownPhoto = PropertyPhoto::factory()->for($property)->create(['position' => 1]);
    $this->actingAs($this->admin);

    $this->patch(route('mk.properties.cover.update', $property), ['photo_id' => $otherPhoto->id])
        ->assertSessionHasErrors('photo_id');
    $this->patch(route('mk.properties.cover.update', $property), ['photo_id' => $ownPhoto->id])
        ->assertSessionHasNoErrors();

    expect($property->fresh()->cover_photo_id)->toBe($ownPhoto->id);
});

it('reorders photos only when the full set is sent', function () {
    $property = Property::factory()->create();
    [$first, $second, $third] = PropertyPhoto::factory()->for($property)->count(3)
        ->sequence(['position' => 0], ['position' => 1], ['position' => 2])->create();
    $this->actingAs($this->admin);

    $this->patch(route('mk.properties.photos.reorder', $property), ['photo_ids' => [$third->id, $first->id]])
        ->assertSessionHasErrors('photo_ids');
    $this->patch(route('mk.properties.photos.reorder', $property), ['photo_ids' => [$third->id, $first->id, $second->id]])
        ->assertSessionHasNoErrors();

    expect($property->photos()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id]);
});

it('deletes a photo file and moves the cover to the next photo', function () {
    $property = Property::factory()->create();
    Storage::disk('public')->put('properties/capa.jpg', 'x');
    $cover = PropertyPhoto::factory()->for($property)->create(['path' => 'properties/capa.jpg', 'position' => 0]);
    $next = PropertyPhoto::factory()->for($property)->create(['position' => 1]);
    $property->forceFill(['cover_photo_id' => $cover->id])->save();

    $this->actingAs($this->admin)
        ->delete(route('mk.properties.photos.destroy', [$property, $cover]))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('properties/capa.jpg');
    expect($property->fresh()->cover_photo_id)->toBe($next->id);
});

it('does not delete a photo through another property', function () {
    $property = Property::factory()->create();
    $foreignPhoto = PropertyPhoto::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('mk.properties.photos.destroy', [$property, $foreignPhoto]))
        ->assertNotFound();

    expect($foreignPhoto->fresh())->not->toBeNull();
});

it('keeps at least one photo on a property visible on the site', function () {
    $property = Property::factory()->published()->create();

    $this->actingAs($this->admin)
        ->delete(route('mk.properties.photos.destroy', [$property, $property->photos()->first()]))
        ->assertSessionHasErrors('photos');

    expect($property->photos()->count())->toBe(1);
});

it('deletes only drafts, removing their files', function () {
    $published = Property::factory()->published()->create();
    $draft = Property::factory()->create();
    Storage::disk('public')->put('properties/rascunho.jpg', 'x');
    PropertyPhoto::factory()->for($draft)->create(['path' => 'properties/rascunho.jpg']);
    $this->actingAs($this->admin);

    $this->delete(route('mk.properties.destroy', $published))->assertSessionHasErrors('property');
    $this->delete(route('mk.properties.destroy', $draft))->assertRedirect(route('mk.properties.index'));

    expect(Property::find($published->id))->not->toBeNull()
        ->and(Property::find($draft->id))->toBeNull();
    Storage::disk('public')->assertMissing('properties/rascunho.jpg');
});

it('assigns an immutable reference code when the property is created', function () {
    $this->actingAs($this->admin)->post(route('mk.properties.store'), ['title' => 'Casa na praia'])
        ->assertSessionHasNoErrors();
    $property = Property::sole();
    $expectedCode = 'MK-'.str_pad((string) $property->id, 5, '0', STR_PAD_LEFT);

    $this->actingAs($this->admin)
        ->patch(route('mk.properties.update', $property), ['title' => 'Casa reformada', 'reference_code' => 'MK-99999'])
        ->assertSessionHasNoErrors();

    expect($property->fresh()->reference_code)->toBe($expectedCode);
});

it('finds a property in the panel by its reference code', function () {
    $match = Property::factory()->create(['title' => 'Sobrado']);
    Property::factory()->create(['title' => 'Terreno']);
    $typedCode = 'mk '.$match->id;

    $this->actingAs($this->admin)->get(route('mk.properties.index', ['search' => $typedCode]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $match->id)
            ->where('properties.data.0.reference_code', $match->reference_code));
});

it('links a listed property to its own page on the public site once it leaves the draft', function () {
    if (! Route::has(Property::PUBLIC_ROUTE)) {
        Route::get('/imoveis/{slug}', fn () => 'ok')->name(Property::PUBLIC_ROUTE);
        Route::getRoutes()->refreshNameLookups();
    }
    $draft = Property::factory()->create();
    $published = Property::factory()->published()->create(['slug' => 'casa-centro-7']);

    $this->actingAs($this->admin)->get(route('mk.properties.edit', $draft))
        ->assertInertia(fn (Assert $page) => $page->where('property.public_url', null));
    $this->actingAs($this->admin)->get(route('mk.properties.edit', $published))
        ->assertInertia(fn (Assert $page) => $page->where('property.public_url', route(Property::PUBLIC_ROUTE, ['slug' => 'casa-centro-7'])));
});
