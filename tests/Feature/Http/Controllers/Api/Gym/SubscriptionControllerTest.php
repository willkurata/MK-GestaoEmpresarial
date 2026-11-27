<?php

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymPlan;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUser;

it('enrols a student and issues the first invoice with the plan price snapshot', function (string $paymentMethod, string $instructionKey) {
    $this->travelTo('2026-11-10 09:00:00');
    $student = GymStudent::factory()->create();
    $plan = GymPlan::factory()->create(['gym_id' => $student->gym_id, 'price' => '120.00', 'billing_interval_months' => 1]);
    $manager = GymUser::factory()->for($student->gym)->create();

    $response = $this->actingAs($manager, 'gym')->postJson(route('academia.students.subscriptions.store', $student->id), [
        'plan_id' => $plan->id,
        'payment_method' => $paymentMethod,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.price', '120.00')
        ->assertJsonPath('data.next_billing_on', '2026-12-10')
        ->assertJsonPath('first_invoice.amount', '120.00')
        ->assertJsonPath('first_invoice.due_on', '2026-11-10')
        ->assertJsonPath('first_invoice.period_end', '2026-12-09')
        ->assertJsonPath('first_invoice.payment_method', $paymentMethod)
        ->assertJsonPath('first_invoice.payment_instructions.environment', 'sandbox');
    expect($response->json('first_invoice.payment_instructions'))->toHaveKey($instructionKey);

    $plan->update(['price' => '150.00']);
    expect($student->subscriptions()->first()->price)->toBe('120.00');
})->with([
    'pix' => ['pix', 'pix_copy_paste'],
    'boleto' => ['boleto', 'digitable_line'],
    'cartão' => ['cartao', 'checkout_reference'],
]);

it('refuses a second active subscription for the same student', function () {
    $subscription = GymSubscription::factory()->create();
    $plan = GymPlan::factory()->create(['gym_id' => $subscription->gym_id]);
    $manager = GymUser::factory()->for($subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.subscriptions.store', $subscription->gym_student_id), [
        'plan_id' => $plan->id,
        'payment_method' => 'pix',
    ])->assertUnprocessable()->assertJsonValidationErrors('student');
});

it('refuses inactive plans and plans of another gym', function (bool $planFromOtherGym) {
    $student = GymStudent::factory()->create();
    $plan = $planFromOtherGym
        ? GymPlan::factory()->create()
        : GymPlan::factory()->inactive()->create(['gym_id' => $student->gym_id]);
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.subscriptions.store', $student->id), [
        'plan_id' => $plan->id,
        'payment_method' => 'pix',
    ])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
})->with(['inactive plan' => false, 'other gym plan' => true]);

it('refuses to enrol an inactive student', function () {
    $student = GymStudent::factory()->inactive()->create();
    $plan = GymPlan::factory()->create(['gym_id' => $student->gym_id]);
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.students.subscriptions.store', $student->id), [
        'plan_id' => $plan->id,
        'payment_method' => 'pix',
    ])->assertUnprocessable()->assertJsonValidationErrors(['student' => 'Reative o aluno antes de matriculá-lo.']);
});

it('cancels the subscription and future invoices while keeping past-due debt', function () {
    $subscription = GymSubscription::factory()->create();
    $overdue = GymInvoice::factory()->for($subscription, 'subscription')->overdueBy(10)->create();
    $upcoming = GymInvoice::factory()->for($subscription, 'subscription')->create([
        'period_start' => today()->addDays(3)->toDateString(),
        'due_on' => today()->addDays(3)->toDateString(),
    ]);
    $manager = GymUser::factory()->for($subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.subscriptions.cancel', $subscription->id), ['reason' => 'Mudou de cidade'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelada')
        ->assertJsonPath('data.cancellation_reason', 'Mudou de cidade');

    expect($upcoming->refresh()->status)->toBe(GymInvoice::STATUS_CANCELED)
        ->and($overdue->refresh()->status)->toBe(GymInvoice::STATUS_PENDING);
});

it('requires a reason to cancel a subscription', function () {
    $subscription = GymSubscription::factory()->create();
    $manager = GymUser::factory()->for($subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.subscriptions.cancel', $subscription->id), [])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('returns not found when cancelling another gym subscription', function () {
    $subscription = GymSubscription::factory()->create();
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->postJson(route('academia.subscriptions.cancel', $subscription->id), ['reason' => 'x'])
        ->assertNotFound();
});
