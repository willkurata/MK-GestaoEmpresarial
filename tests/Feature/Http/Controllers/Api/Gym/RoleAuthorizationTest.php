<?php

use App\Models\Gym\GymUser;

it('forbids students from staff endpoints', function () {
    $student = GymUser::factory()->student()->create();

    $this->actingAs($student, 'gym')->getJson(route('academia.students.index'))->assertForbidden();
});

it('forbids staff from the student area', function () {
    $manager = GymUser::factory()->manager()->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.me.profile'))->assertForbidden();
});

it('forbids managers from administrator-only endpoints', function (string $method, string $routeName, array $parameters) {
    $manager = GymUser::factory()->manager()->create();

    $this->actingAs($manager, 'gym')->json($method, route($routeName, $parameters))->assertForbidden();
})->with([
    'create plan' => ['POST', 'academia.plans.store', []],
    'create unit' => ['POST', 'academia.units.store', []],
    'create staff' => ['POST', 'academia.staff.store', []],
    'refund payment' => ['POST', 'academia.payments.refund', ['payment' => 1]],
    'cancel invoice' => ['POST', 'academia.invoices.cancel', ['invoice' => 1]],
    'export personal data' => ['GET', 'academia.students.personal-data', ['student' => 1]],
    'anonymize student' => ['POST', 'academia.students.anonymize', ['student' => 1]],
    'register turnstile' => ['POST', 'academia.access-devices.store', []],
]);
