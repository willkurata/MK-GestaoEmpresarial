<?php

use App\Models\CyberCoffe\ApiToken;
use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Customer;
use App\Models\CyberCoffe\Store;

it('logs an attendant in and returns a bearer token that authenticates', function () {
    $store = Store::factory()->create(['code' => 'centro']);
    $attendant = Attendant::factory()->for($store)->create(['username' => 'ana']);

    $response = $this->postJson(route('cybercoffe.auth.attendants.login'), [
        'store_code' => 'Centro',
        'username' => 'ana',
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonPath('attendant.id', $attendant->id)->assertJsonPath('store.code', 'centro');
    $this->getJson(route('cybercoffe.auth.me'), ['Authorization' => 'Bearer '.$response->json('token')])
        ->assertOk()
        ->assertJsonPath('attendant.username', 'ana');
    expect(ApiToken::first()->token)->not->toBe($response->json('token'));
});

it('rejects attendant login with wrong credentials, another store or an inactive account', function (array $payload) {
    $store = Store::factory()->create(['code' => 'centro']);
    Attendant::factory()->for($store)->create(['username' => 'ana']);
    Attendant::factory()->for($store)->inactive()->create(['username' => 'bia']);
    Store::factory()->create(['code' => 'norte']);
    Store::factory()->inactive()->create(['code' => 'fechada']);

    $this->postJson(route('cybercoffe.auth.attendants.login'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username' => 'Credenciais inválidas.']);
    $this->assertDatabaseCount('cc_api_tokens', 0);
})->with([
    'wrong password' => [['store_code' => 'centro', 'username' => 'ana', 'password' => 'errada']],
    'other store' => [['store_code' => 'norte', 'username' => 'ana', 'password' => 'password']],
    'inactive attendant' => [['store_code' => 'centro', 'username' => 'bia', 'password' => 'password']],
    'inactive store' => [['store_code' => 'fechada', 'username' => 'ana', 'password' => 'password']],
]);

it('returns 401 without a token, with an expired token or with a customer token', function () {
    $store = Store::factory()->create();
    $attendant = Attendant::factory()->for($store)->create();
    $customer = Customer::factory()->for($store)->create();
    $expired = ApiToken::issue($attendant, 'teste', now()->subMinute());

    $this->getJson(route('cybercoffe.stations.index'))->assertUnauthorized();
    $this->getJson(route('cybercoffe.stations.index'), ['Authorization' => "Bearer {$expired}"])->assertUnauthorized();
    $this->getJson(route('cybercoffe.stations.index'), cyberCoffeAuth($customer))->assertUnauthorized();
});

it('stops accepting tokens of an attendant whose store was deactivated', function () {
    $store = Store::factory()->create();
    $headers = cyberCoffeAuth(Attendant::factory()->for($store)->create());

    $store->update(['status' => 'inativa']);

    $this->getJson(route('cybercoffe.stations.index'), $headers)->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $headers = cyberCoffeAuth(Attendant::factory()->create());

    $this->postJson(route('cybercoffe.auth.logout'), [], $headers)->assertOk();

    $this->assertDatabaseCount('cc_api_tokens', 0);
    $this->getJson(route('cybercoffe.auth.me'), $headers)->assertUnauthorized();
});

it('forbids a regular attendant from manager endpoints', function () {
    $headers = cyberCoffeAuth(Attendant::factory()->create());

    $this->postJson(route('cybercoffe.stations.store'), ['name' => 'PC-01', 'type' => 'pc'], $headers)
        ->assertForbidden()
        ->assertJsonPath('message', 'Apenas gerentes podem realizar esta ação.');
    $this->assertDatabaseCount('cc_stations', 0);
});

it('registers a customer with a normalized phone and lets them log in', function () {
    $store = Store::factory()->create(['code' => 'centro']);

    $this->postJson(route('cybercoffe.auth.customers.register'), [
        'store_code' => 'centro',
        'name' => 'João',
        'phone' => '(11) 98888-7777',
        'password' => 'segredo123',
        'password_confirmation' => 'segredo123',
    ])->assertCreated()->assertJsonPath('customer.phone', '11988887777');

    $this->assertDatabaseHas('cc_customers', ['store_id' => $store->id, 'phone' => '11988887777']);

    $login = $this->postJson(route('cybercoffe.auth.customers.login'), [
        'store_code' => 'centro',
        'phone' => '11988887777',
        'password' => 'segredo123',
    ])->assertOk();

    $this->getJson(route('cybercoffe.me.show'), ['Authorization' => 'Bearer '.$login->json('token')])
        ->assertOk()
        ->assertJsonPath('data.name', 'João')
        ->assertJsonPath('data.credit_balance', '0.00');
});

it('rejects a customer phone already registered in the same store', function () {
    $store = Store::factory()->create(['code' => 'centro']);
    Customer::factory()->for($store)->create(['phone' => '11988887777']);

    $this->postJson(route('cybercoffe.auth.customers.register'), [
        'store_code' => 'centro',
        'name' => 'João',
        'phone' => '11988887777',
        'password' => 'segredo123',
        'password_confirmation' => 'segredo123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
});
