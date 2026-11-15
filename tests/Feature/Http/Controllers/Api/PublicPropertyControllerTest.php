<?php

use App\Models\Property;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('lists only properties visible on the site, published first', function () {
    Property::factory()->create(['title' => 'Rascunho oculto']);
    $sold = Property::factory()->published()->create(['title' => 'Vendido', 'status' => 'vendido']);
    $published = Property::factory()->published()->create(['title' => 'Publicado']);

    $this->getJson(route('api.properties.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.1.id', $sold->id)
        ->assertJsonPath('data.0.cover_photo.url', $published->coverPhoto->url())
        ->assertJsonMissingPath('data.0.photos')
        ->assertJsonPath('meta.total', 2);
});

it('filters the public list', function () {
    Property::factory()->published()->create(['city' => 'Londrina', 'price' => 300000]);
    $match = Property::factory()->forRent()->published()->create(['city' => 'Londrina', 'price' => 2500]);
    Property::factory()->forRent()->published()->create(['city' => 'Maringá', 'price' => 2000]);

    $this->getJson(route('api.properties.index', ['purpose' => 'aluguel', 'city' => 'Londrina', 'max_price' => 3000]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);

    $this->getJson(route('api.properties.index', ['status' => 'rascunho']))->assertUnprocessable();
});

it('shows a public property by slug with its ordered photos', function () {
    $property = Property::factory()->published()->create(['slug' => 'casa-centro-10']);

    $this->getJson(route('api.properties.show', 'casa-centro-10'))
        ->assertOk()
        ->assertJsonPath('data.id', $property->id)
        ->assertJsonPath('data.status', 'publicado')
        ->assertJsonCount(1, 'data.photos')
        ->assertJsonPath('data.cover_photo.id', $property->cover_photo_id);
});

it('never exposes a draft by slug', function () {
    Property::factory()->create(['slug' => 'rascunho-1']);

    $this->getJson(route('api.properties.show', 'rascunho-1'))->assertNotFound();
});
