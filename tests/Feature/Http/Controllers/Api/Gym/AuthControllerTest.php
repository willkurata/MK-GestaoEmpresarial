<?php

use App\Models\Gym\Gym;
use App\Models\Gym\GymUser;

it('issues a bearer token that authenticates later requests', function () {
    $gym = Gym::factory()->create(['slug' => 'academia-centro']);
    $user = GymUser::factory()->for($gym)->administrator()->create(['email' => 'admin@academia.test']);

    $response = $this->postJson(route('academia.auth.login'), [
        'gym' => 'academia-centro',
        'email' => 'admin@academia.test',
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonPath('user.role', 'administrador')->assertJsonPath('token_type', 'Bearer');
    $token = $response->json('token');
    expect($user->apiTokens()->first()->token_hash)->toBe(hash('sha256', $token));

    $this->withToken($token)->getJson(route('academia.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.gym.slug', 'academia-centro');
});

it('rejects a wrong password', function () {
    $gym = Gym::factory()->create(['slug' => 'academia-centro']);
    GymUser::factory()->for($gym)->create(['email' => 'gestor@academia.test']);

    $this->postJson(route('academia.auth.login'), [
        'gym' => 'academia-centro',
        'email' => 'gestor@academia.test',
        'password' => 'senha-errada',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Academia, e-mail ou senha inválidos.']);
});

it('does not let a user log in through another gym', function () {
    Gym::factory()->create(['slug' => 'academia-norte']);
    $gym = Gym::factory()->create(['slug' => 'academia-sul']);
    GymUser::factory()->for($gym)->create(['email' => 'gestor@academia.test']);

    $this->postJson(route('academia.auth.login'), [
        'gym' => 'academia-norte',
        'email' => 'gestor@academia.test',
        'password' => 'password',
    ])->assertUnprocessable();
});

it('blocks inactive users and inactive gyms from logging in', function (bool $inactiveGym) {
    $gym = $inactiveGym ? Gym::factory()->inactive()->create(['slug' => 'academia']) : Gym::factory()->create(['slug' => 'academia']);
    $factory = GymUser::factory()->for($gym);
    ($inactiveGym ? $factory : $factory->inactive())->create(['email' => 'gestor@academia.test']);

    $this->postJson(route('academia.auth.login'), [
        'gym' => 'academia',
        'email' => 'gestor@academia.test',
        'password' => 'password',
    ])->assertUnprocessable();
})->with(['inactive user' => false, 'inactive gym' => true]);

it('throttles repeated login attempts', function () {
    $gym = Gym::factory()->create(['slug' => 'academia']);
    GymUser::factory()->for($gym)->create(['email' => 'gestor@academia.test']);
    $payload = ['gym' => 'academia', 'email' => 'gestor@academia.test', 'password' => 'errada'];

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('academia.auth.login'), $payload)->assertUnprocessable();
    }

    $this->postJson(route('academia.auth.login'), $payload)->assertTooManyRequests();
});

it('returns unauthorized without a valid token', function (?string $token) {
    $request = $token === null ? $this : $this->withToken($token);

    $request->getJson(route('academia.auth.me'))->assertUnauthorized();
})->with(['missing token' => null, 'unknown token' => 'token-invalido']);

it('rejects an expired token', function () {
    $user = GymUser::factory()->create();
    $token = $user->issueApiToken('app');
    $this->travel(31)->days();

    $this->withToken($token)->getJson(route('academia.auth.me'))->assertUnauthorized();
});

it('rejects the token of a user who was deactivated', function () {
    $user = GymUser::factory()->create();
    $token = $user->issueApiToken('app');
    $user->update(['status' => 'inativo']);

    $this->withToken($token)->getJson(route('academia.auth.me'))->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = GymUser::factory()->create();
    $token = $user->issueApiToken('app');

    $this->withToken($token)->postJson(route('academia.auth.logout'))->assertNoContent();

    expect($user->apiTokens()->count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson(route('academia.auth.me'))->assertUnauthorized();
});
