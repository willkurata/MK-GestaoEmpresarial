<?php

use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUser;

function studentAccount(): array
{
    $account = GymUser::factory()->student()->create();
    $student = GymStudent::factory()->create(['gym_id' => $account->gym_id]);
    $student->forceFill(['gym_user_id' => $account->id])->save();

    return [$account, $student];
}

it('shows the student profile with the active subscription', function () {
    [$account, $student] = studentAccount();
    $subscription = GymSubscription::factory()->create(['gym_student_id' => $student->id]);

    $this->actingAs($account, 'gym')->getJson(route('academia.me.profile'))
        ->assertOk()
        ->assertJsonPath('data.id', $student->id)
        ->assertJsonPath('subscription.id', $subscription->id);
});

it('lists only the invoices of the signed-in student', function () {
    [$account, $student] = studentAccount();
    $subscription = GymSubscription::factory()->create(['gym_student_id' => $student->id]);
    $own = GymInvoice::factory()->create(['gym_subscription_id' => $subscription->id]);
    $classmate = GymStudent::factory()->create(['gym_id' => $student->gym_id, 'gym_unit_id' => $student->gym_unit_id]);
    GymInvoice::factory()->create(['gym_subscription_id' => GymSubscription::factory()->create(['gym_student_id' => $classmate->id])->id]);

    $this->actingAs($account, 'gym')->getJson(route('academia.me.invoices'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});
