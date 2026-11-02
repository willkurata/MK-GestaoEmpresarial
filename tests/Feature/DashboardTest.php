<?php

use App\Models\User;

test('guests are redirected to the MK login page', function () {
    $response = $this->get(route('mk.dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('mk.dashboard'));
    $response->assertOk();
});
