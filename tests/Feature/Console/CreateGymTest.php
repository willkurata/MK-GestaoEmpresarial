<?php

use App\Models\Gym\Gym;
use Illuminate\Support\Facades\Hash;

it('creates a gym with its first unit and administrator', function () {
    $this->artisan('academia:create-gym')
        ->expectsQuestion('Nome da academia', 'Academia Força Total')
        ->expectsQuestion('Nome da primeira unidade', 'Centro')
        ->expectsQuestion('Nome do administrador', 'Rita Admin')
        ->expectsQuestion('E-mail do administrador', 'Rita@Forca.test')
        ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'senha-muito-forte')
        ->expectsQuestion('Confirme a senha', 'senha-muito-forte')
        ->expectsOutput('Academia criada. Identificador para login: academia-forca-total')
        ->assertSuccessful();

    $gym = Gym::query()->where('slug', 'academia-forca-total')->sole();
    $admin = $gym->users()->sole();
    expect($gym->units()->sole()->name)->toBe('Centro')
        ->and($admin->role)->toBe('administrador')
        ->and($admin->email)->toBe('rita@forca.test')
        ->and(Hash::check('senha-muito-forte', $admin->password))->toBeTrue();
});

it('refuses a weak administrator password', function () {
    $this->artisan('academia:create-gym')
        ->expectsQuestion('Nome da academia', 'Academia Nova')
        ->expectsQuestion('Nome da primeira unidade', 'Centro')
        ->expectsQuestion('Nome do administrador', 'Rita')
        ->expectsQuestion('E-mail do administrador', 'rita@nova.test')
        ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'curta')
        ->expectsQuestion('Confirme a senha', 'curta')
        ->assertFailed();

    expect(Gym::query()->count())->toBe(0);
});
