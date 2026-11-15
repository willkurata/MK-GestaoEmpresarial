<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('public site pages render without authentication', function (string $routeName, string $component) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertViewIs('site')
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'home' => ['site.home', 'site/Home'],
    'properties' => ['site.properties.index', 'site/properties/Index'],
    'accounting' => ['site.accounting', 'site/Accounting'],
    'business' => ['site.business', 'site/Business'],
    'about' => ['site.about', 'site/About'],
    'contact' => ['site.contact', 'site/Contact'],
    'privacy' => ['site.privacy', 'site/Privacy'],
]);

test('public site shares contact data but never MK Gestão authentication', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('site.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.contact.whatsapp', '5512997298895')
            ->where('site.contact.email', 'contato@mknegocios.com')
            ->where('site.cities', ['Ubatuba', 'Caraguatatuba'])
            ->missing('auth')
            ->missing('flash'));
});

test('MK Gestão keeps its own root view at the application home', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertViewIs('app')
        ->assertInertia(fn (Assert $page) => $page->component('auth/CompanyLogin'));
});

test('property listing exposes the paginated contract and echoes valid filters', function () {
    $this->get(route('site.properties.index', ['finalidade' => 'venda', 'cidade' => 'Ubatuba']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('site/properties/Index')
            ->has('properties.data', 0)
            ->where('properties.total', 0)
            ->where('filters', ['finalidade' => 'venda', 'cidade' => 'Ubatuba', 'tipo' => null])
            ->where('filter_options.cities', ['Ubatuba', 'Caraguatatuba']));
});

test('property listing rejects an unknown purpose', function () {
    $this->get(route('site.properties.index', ['finalidade' => 'permuta']))
        ->assertRedirect()
        ->assertSessionHasErrors('finalidade');
});

test('property detail returns not found for unpublished slugs', function () {
    $this->get(route('site.properties.show', 'casa-inexistente'))
        ->assertNotFound();
});
