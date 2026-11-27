<?php

use App\Models\Gym\Gym;
use App\Models\Gym\GymPlan;
use App\Models\Gym\GymUnit;
use App\Models\Gym\GymUser;

it('lets an administrator create a plan', function () {
    $admin = GymUser::factory()->administrator()->create();

    $this->actingAs($admin, 'gym')->postJson(route('academia.plans.store'), [
        'name' => 'Trimestral',
        'price' => '249.90',
        'billing_interval_months' => 3,
        'grace_days' => 7,
    ])->assertCreated()->assertJsonPath('data.price', '249.90');

    $this->assertDatabaseHas('gym_plans', ['gym_id' => $admin->gym_id, 'name' => 'Trimestral', 'billing_interval_months' => 3]);
});

it('rejects an unsupported billing interval', function () {
    $admin = GymUser::factory()->administrator()->create();

    $this->actingAs($admin, 'gym')->postJson(route('academia.plans.store'), [
        'name' => 'Bimestral',
        'price' => '150',
        'billing_interval_months' => 2,
    ])->assertUnprocessable()->assertJsonValidationErrors('billing_interval_months');
});

it('shows students only the active plans of their gym', function () {
    $gym = Gym::factory()->create();
    $active = GymPlan::factory()->for($gym)->create();
    GymPlan::factory()->for($gym)->inactive()->create();
    GymPlan::factory()->create();
    $student = GymUser::factory()->for($gym)->student()->create();

    $this->actingAs($student, 'gym')->getJson(route('academia.plans.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});

it('returns not found when updating another gym plan', function () {
    $otherPlan = GymPlan::factory()->create();
    $admin = GymUser::factory()->administrator()->create();

    $this->actingAs($admin, 'gym')->putJson(route('academia.plans.update', $otherPlan->id), [
        'name' => 'Invasão',
        'price' => '1',
        'billing_interval_months' => 1,
    ])->assertNotFound();
});

it('lets an administrator add units with unique names per gym', function () {
    $admin = GymUser::factory()->administrator()->create();
    GymUnit::factory()->create(['gym_id' => $admin->gym_id, 'name' => 'Centro']);
    GymUnit::factory()->create(['name' => 'Zona Sul']);

    $this->actingAs($admin, 'gym')->postJson(route('academia.units.store'), ['name' => 'Centro'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->actingAs($admin, 'gym')->postJson(route('academia.units.store'), ['name' => 'Zona Sul', 'capacity' => 120])
        ->assertCreated()->assertJsonPath('data.capacity', 120);
});

it('lets an administrator create a manager bound to a unit', function () {
    $admin = GymUser::factory()->administrator()->create();
    $unit = GymUnit::factory()->create(['gym_id' => $admin->gym_id]);

    $this->actingAs($admin, 'gym')->postJson(route('academia.staff.store'), [
        'name' => 'Paula Gestora',
        'email' => 'Paula@Academia.test',
        'password' => 'senha-forte-123',
        'role' => 'gestor',
        'unit_id' => $unit->id,
    ])->assertCreated()->assertJsonMissingPath('data.password');

    $this->assertDatabaseHas('gym_users', ['gym_id' => $admin->gym_id, 'email' => 'paula@academia.test', 'role' => 'gestor', 'gym_unit_id' => $unit->id]);
});

it('does not allow staff accounts with the student role', function () {
    $admin = GymUser::factory()->administrator()->create();

    $this->actingAs($admin, 'gym')->postJson(route('academia.staff.store'), [
        'name' => 'Alguém',
        'email' => 'alguem@academia.test',
        'password' => 'senha-forte-123',
        'role' => 'aluno',
    ])->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('revokes the tokens of a deactivated staff member', function () {
    $admin = GymUser::factory()->administrator()->create();
    $manager = GymUser::factory()->for($admin->gym)->manager()->create();
    $manager->issueApiToken('app');

    $this->actingAs($admin, 'gym')->patchJson(route('academia.staff.update', $manager->id), ['status' => 'inativo'])->assertOk();

    expect($manager->refresh()->status)->toBe('inativo')
        ->and($manager->apiTokens()->count())->toBe(0);
});

it('prevents an administrator from removing their own access', function () {
    $admin = GymUser::factory()->administrator()->create();

    $this->actingAs($admin, 'gym')->patchJson(route('academia.staff.update', $admin->id), ['role' => 'gestor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role' => 'Você não pode remover o próprio acesso de administrador.']);
});
