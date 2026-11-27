<?php

use App\Models\Gym\GymAccessEvent;
use App\Models\Gym\GymClass;
use App\Models\Gym\GymClassAttendance;
use App\Models\Gym\GymInvoice;
use App\Models\Gym\GymStudent;
use App\Models\Gym\GymSubscription;
use App\Models\Gym\GymUser;

it('summarises students, delinquency and the month on the dashboard', function () {
    $this->travelTo('2026-11-23 12:00:00');
    $subscription = GymSubscription::factory()->create();
    $gym = $subscription->student->gym;
    GymStudent::factory()->inactive()->create(['gym_id' => $gym->id, 'gym_unit_id' => $subscription->student->gym_unit_id]);
    GymInvoice::factory()->overdueBy(10)->create(['gym_subscription_id' => $subscription->id, 'amount' => '100.00']);
    $paid = GymInvoice::factory()->paid()->create(['gym_subscription_id' => $subscription->id, 'period_start' => '2026-11-01', 'amount' => '80.00']);
    $paid->payments()->create(['gym_id' => $gym->id, 'method' => 'pix', 'amount' => '80.00', 'source' => 'manual', 'paid_at' => '2026-11-05 10:00:00']);
    GymInvoice::factory()->overdueBy(10)->create();
    $manager = GymUser::factory()->for($gym)->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.students.active', 1)
        ->assertJsonPath('data.students.inactive', 1)
        ->assertJsonPath('data.delinquency.overdue_invoices', 1)
        ->assertJsonPath('data.delinquency.overdue_amount', '100.00')
        ->assertJsonPath('data.delinquency.students_overdue', 1)
        ->assertJsonPath('data.month.received', '80.00');
});

it('lists overdue invoices with days late and the delinquency rate', function () {
    $this->travelTo('2026-11-23 12:00:00');
    $subscription = GymSubscription::factory()->create();
    GymStudent::factory()->create(['gym_id' => $subscription->gym_id, 'gym_unit_id' => $subscription->student->gym_unit_id]);
    $overdue = GymInvoice::factory()->overdueBy(12)->create(['gym_subscription_id' => $subscription->id, 'amount' => '99.90']);
    $manager = GymUser::factory()->for($subscription->student->gym)->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.reports.delinquency'))
        ->assertOk()
        ->assertJsonPath('data.overdue_amount', '99.90')
        ->assertJsonPath('data.delinquency_rate', 50)
        ->assertJsonPath('data.invoices.0.id', $overdue->id)
        ->assertJsonPath('data.invoices.0.days_late', 12);
});

it('reports cash flow per day with refunds as outflows', function () {
    $invoice = GymInvoice::factory()->create(['amount' => '100.00']);
    $gymId = $invoice->gym_id;
    $invoice->payments()->create(['gym_id' => $gymId, 'method' => 'pix', 'amount' => '100.00', 'source' => 'gateway', 'paid_at' => '2026-11-02 09:00:00']);
    $second = GymInvoice::factory()->create(['gym_subscription_id' => $invoice->gym_subscription_id, 'period_start' => '2026-12-01', 'amount' => '60.00']);
    $second->payments()->create([
        'gym_id' => $gymId, 'method' => 'cartao', 'amount' => '60.00', 'source' => 'manual', 'paid_at' => '2026-11-02 11:00:00',
        'status' => 'estornado', 'refunded_at' => '2026-11-03 08:00:00', 'refund_reason' => 'Duplicado',
    ]);
    $manager = GymUser::factory()->for($invoice->subscription->student->gym)->create();

    $response = $this->actingAs($manager, 'gym')->getJson(route('academia.reports.cash-flow', ['from' => '2026-11-01', 'to' => '2026-11-03']));

    $response->assertOk()
        ->assertJsonPath('data.received', '160.00')
        ->assertJsonPath('data.refunded', '60.00')
        ->assertJsonPath('data.net', '100.00')
        ->assertJsonPath('data.received_by_method.pix', '100.00')
        ->assertJsonPath('data.received_by_method.cartao', '60.00')
        ->assertJsonPath('data.by_day.1', ['date' => '2026-11-02', 'received' => '160.00', 'refunded' => '0.00', 'net' => '160.00'])
        ->assertJsonPath('data.by_day.2', ['date' => '2026-11-03', 'received' => '0.00', 'refunded' => '60.00', 'net' => '-60.00']);
});

it('rejects a cash-flow period that ends before it starts', function () {
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.reports.cash-flow', ['from' => '2026-11-10', 'to' => '2026-11-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('to');
});

it('reports entrances per day and per hour', function () {
    $student = GymStudent::factory()->create();
    foreach (['2026-11-20 07:10:00', '2026-11-20 07:40:00', '2026-11-21 18:00:00'] as $at) {
        GymAccessEvent::create([
            'gym_id' => $student->gym_id, 'gym_unit_id' => $student->gym_unit_id, 'gym_student_id' => $student->id,
            'direction' => 'entrada', 'method' => 'facial', 'decision' => 'liberado', 'occurred_at' => $at,
        ]);
    }
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.reports.occupancy', ['from' => '2026-11-20', 'to' => '2026-11-21']))
        ->assertOk()
        ->assertJsonPath('data.total_entries', 3)
        ->assertJsonPath('data.by_day.0', ['date' => '2026-11-20', 'entries' => 2, 'exits' => 0, 'denied' => 0, 'unique_students' => 1])
        ->assertJsonPath('data.by_hour.7.entries', 2)
        ->assertJsonPath('data.by_hour.18.entries', 1);
});

it('reports exits and denied attempts by reason in the turnstile flow', function () {
    $student = GymStudent::factory()->create();
    foreach ([['entrada', 'liberado', null], ['saida', 'liberado', null], ['entrada', 'negado', 'mensalidade_em_atraso'], ['entrada', 'negado', 'mensalidade_em_atraso']] as [$direction, $decision, $reason]) {
        GymAccessEvent::create([
            'gym_id' => $student->gym_id, 'gym_unit_id' => $student->gym_unit_id, 'gym_student_id' => $student->id,
            'direction' => $direction, 'method' => 'facial', 'decision' => $decision, 'reason' => $reason, 'occurred_at' => '2026-11-20 08:00:00',
        ]);
    }
    $manager = GymUser::factory()->for($student->gym)->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.reports.occupancy', ['from' => '2026-11-20', 'to' => '2026-11-20']))
        ->assertOk()
        ->assertJsonPath('data.total_entries', 1)
        ->assertJsonPath('data.total_exits', 1)
        ->assertJsonPath('data.total_denied', 2)
        ->assertJsonPath('data.denied_by_reason', ['mensalidade_em_atraso' => 2])
        ->assertJsonPath('data.by_day.0', ['date' => '2026-11-20', 'entries' => 1, 'exits' => 1, 'denied' => 2, 'unique_students' => 1]);
});

it('reports frequency per student and per class', function () {
    $class = GymClass::factory()->create();
    [$assiduous, $absent] = GymStudent::factory()->forUnit($class->unit)->count(2)->create()->all();
    foreach (['2026-11-18', '2026-11-20'] as $date) {
        GymClassAttendance::create(['gym_id' => $class->gym_id, 'gym_class_id' => $class->id, 'gym_student_id' => $assiduous->id, 'attended_on' => $date, 'present' => true]);
        GymClassAttendance::create(['gym_id' => $class->gym_id, 'gym_class_id' => $class->id, 'gym_student_id' => $absent->id, 'attended_on' => $date, 'present' => false]);
    }
    foreach (['2026-11-18 19:00:00', '2026-11-18 21:00:00', '2026-11-20 19:00:00'] as $at) {
        GymAccessEvent::create([
            'gym_id' => $class->gym_id, 'gym_unit_id' => $class->gym_unit_id, 'gym_student_id' => $assiduous->id,
            'direction' => 'entrada', 'method' => 'facial', 'decision' => 'liberado', 'occurred_at' => $at,
        ]);
    }
    $manager = GymUser::factory()->for($class->gym)->create();

    $response = $this->actingAs($manager, 'gym')->getJson(route('academia.reports.attendance', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk();

    $response->assertJsonPath('data.by_class.0.sessions', 2)
        ->assertJsonPath('data.by_class.0.presences', 2)
        ->assertJsonPath('data.by_class.0.absences', 2)
        ->assertJsonPath('data.by_class.0.attendance_rate', 50);
    $byStudent = collect($response->json('data.by_student'))->keyBy('student_id');
    expect($byStudent[$assiduous->id])->toMatchArray(['visit_days' => 2, 'class_presences' => 2, 'class_absences' => 0, 'class_attendance_rate' => 100.0])
        ->and($byStudent[$absent->id])->toMatchArray(['visit_days' => 0, 'class_presences' => 0, 'class_absences' => 2, 'class_attendance_rate' => 0.0]);
});

it('keeps other gyms out of the frequency report', function () {
    $class = GymClass::factory()->create();
    $student = GymStudent::factory()->forUnit($class->unit)->create();
    GymClassAttendance::create(['gym_id' => $class->gym_id, 'gym_class_id' => $class->id, 'gym_student_id' => $student->id, 'attended_on' => today(), 'present' => true]);
    $manager = GymUser::factory()->create();

    $this->actingAs($manager, 'gym')->getJson(route('academia.reports.attendance'))
        ->assertOk()->assertJsonCount(0, 'data.by_student')->assertJsonCount(0, 'data.by_class');
});
