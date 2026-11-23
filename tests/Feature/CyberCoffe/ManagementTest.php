<?php

use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Plan;
use App\Models\CyberCoffe\Store;
use Illuminate\Support\Facades\Hash;

it('creates package and open plans with the fields each billing requires', function () {
    $manager = Attendant::factory()->manager()->create();
    $headers = cyberCoffeAuth($manager);

    $this->postJson(route('cybercoffe.plans.store'), ['name' => '1 hora', 'billing' => 'pacote', 'price' => '6.00', 'minutes' => 60], $headers)
        ->assertCreated();
    $this->postJson(route('cybercoffe.plans.store'), ['name' => 'Livre', 'billing' => 'avulso', 'hourly_rate' => '5.00'], $headers)
        ->assertCreated();
    $this->postJson(route('cybercoffe.plans.store'), ['name' => 'Errado', 'billing' => 'avulso', 'hourly_rate' => '5.00', 'minutes' => 60], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['minutes']);
    $this->postJson(route('cybercoffe.plans.store'), ['name' => 'Sem preço', 'billing' => 'pacote'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price', 'minutes']);

    expect(Plan::where('store_id', $manager->store_id)->count())->toBe(2);
});

it('lists only active plans of the store by default', function () {
    $attendant = Attendant::factory()->create();
    Plan::factory()->for($attendant->store)->create(['name' => 'Ativo']);
    Plan::factory()->for($attendant->store)->inactive()->create(['name' => 'Inativo']);
    Plan::factory()->create(['name' => 'Outra loja']);

    $this->getJson(route('cybercoffe.plans.index'), cyberCoffeAuth($attendant))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ativo');
});

it('lets a manager create attendants and deactivating one revokes their tokens', function () {
    $manager = Attendant::factory()->manager()->create();
    $headers = cyberCoffeAuth($manager);

    $attendantId = $this->postJson(route('cybercoffe.attendants.store'), [
        'name' => 'Carlos',
        'username' => 'carlos',
        'password' => 'senha-forte-1',
        'role' => 'atendente',
    ], $headers)->assertCreated()->assertJsonMissingPath('data.password')->json('data.id');

    $attendant = Attendant::findOrFail($attendantId);
    $attendantHeaders = cyberCoffeAuth($attendant);

    $this->patchJson(route('cybercoffe.attendants.update', $attendantId), ['status' => 'inativo'], $headers)->assertOk();

    $this->getJson(route('cybercoffe.auth.me'), $attendantHeaders)->assertUnauthorized();
});

it('does not let a manager deactivate their own account', function () {
    $manager = Attendant::factory()->manager()->create();

    $this->patchJson(route('cybercoffe.attendants.update', $manager->id), ['status' => 'inativo'], cyberCoffeAuth($manager))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('returns 404 when updating an attendant of another store', function () {
    $manager = Attendant::factory()->manager()->create();
    $other = Attendant::factory()->create();

    $this->patchJson(route('cybercoffe.attendants.update', $other->id), ['status' => 'inativo'], cyberCoffeAuth($manager))
        ->assertNotFound();
    expect($other->fresh()->status)->toBe('ativo');
});

it('creates a store and its manager from the console', function () {
    $this->artisan('cybercoffe:create-store')
        ->expectsQuestion('Código da loja (usado no login, ex.: centro)', 'Centro')
        ->expectsQuestion('Nome da loja', 'Cyber Centro')
        ->expectsQuestion('Nome do gerente', 'Gerente')
        ->expectsQuestion('Usuário do gerente', 'gerente')
        ->expectsQuestion('Senha do gerente (mínimo 12 caracteres)', 'senha-muito-forte')
        ->expectsQuestion('Confirme a senha', 'senha-muito-forte')
        ->assertSuccessful();

    $store = Store::where('code', 'centro')->firstOrFail();
    $manager = $store->attendants()->firstOrFail();
    expect($manager->role)->toBe('gerente')
        ->and(Hash::check('senha-muito-forte', $manager->password))->toBeTrue();
});
