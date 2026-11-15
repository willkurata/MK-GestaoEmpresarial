<?php

use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PropertyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site público MK Negócios
|--------------------------------------------------------------------------
|
| Carregado por routes/web.php dentro de um grupo com domínio/prefixo de
| config/site.php e nomes "site.*". Nenhuma rota aqui exige autenticação
| nem toca dados do MK Gestão.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/imoveis', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/imoveis/{slug}', [PropertyController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('properties.show');

Route::inertia('/contabilidade', 'site/Accounting')->name('accounting');
Route::inertia('/empresas', 'site/Business')->name('business');
Route::inertia('/sobre', 'site/About')->name('about');
Route::inertia('/contato', 'site/Contact')->name('contact');
Route::inertia('/privacidade', 'site/Privacy')->name('privacy');
