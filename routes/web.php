<?php

use App\Http\Controllers\Auth\CompanyAuthController;
use App\Http\Controllers\Auth\CompanyUserAuthController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\CompanyUserSecurityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\Mk\CompanyController as MkCompanyController;
use App\Http\Controllers\Mk\DashboardController as MkDashboardController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ReportsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [CompanyAuthController::class, 'create'])->name('home');
Route::post('/company/login', [CompanyAuthController::class, 'store'])
    ->middleware('throttle:company-login')
    ->name('company.login');
Route::post('/company/logout', [CompanyAuthController::class, 'destroy'])->name('company.logout');

Route::middleware('company.auth')->group(function () {
    Route::get('/company/portal', fn (Request $request) => Inertia::render('company/Portal', [
        'company' => $request->user('company')->only(['name', 'cnpj']),
    ]))->name('company.portal');
    Route::post('/company/user/login', [CompanyUserAuthController::class, 'store'])
        ->middleware('throttle:company-user-login')
        ->name('company.user.login');
    Route::post('/company/user/logout', [CompanyUserAuthController::class, 'destroy'])->name('company.user.logout');
});

Route::middleware(['company.auth', 'company-user.auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/minha-seguranca', [CompanyUserSecurityController::class, 'edit'])->name('company.user.security.edit');
    Route::put('/minha-senha', [CompanyUserSecurityController::class, 'update'])->middleware('throttle:6,1')->name('company.user.password.update');

    Route::get('/produtos', [ProductsController::class, 'index'])->name('products.index');
    Route::post('/produtos', [ProductsController::class, 'store'])->name('products.store');
    Route::patch('/produtos/{product}', [ProductsController::class, 'update'])->name('products.update');

    Route::get('/despesas', [ExpensesController::class, 'index'])->name('expenses.index');
    Route::post('/despesas', [ExpensesController::class, 'store'])->name('expenses.store');
    Route::patch('/despesas/{expense}', [ExpensesController::class, 'update'])->name('expenses.update');

    Route::get('/relatorios', ReportsController::class)->name('reports.index');

    Route::get('/caixa', [CashierController::class, 'index'])->name('pos.index');
    Route::post('/caixa/abrir', [CashierController::class, 'open'])->name('pos.open');
    Route::post('/caixa/vendas', [CashierController::class, 'storeSale'])->name('pos.sales.store');
    Route::post('/caixa/retiradas', [CashierController::class, 'withdraw'])->name('pos.withdraw');
    Route::post('/caixa/vendas/{sale}/estorno', [CashierController::class, 'refund'])
        ->middleware('company.can:reverse-sales')
        ->name('pos.sales.refund');
    Route::post('/caixa/fechar', [CashierController::class, 'close'])->name('pos.close');
});

Route::middleware(['auth', 'verified'])->prefix('mk')->name('mk.')->group(function () {
    Route::get('/dashboard', MkDashboardController::class)->name('dashboard');
    Route::get('/companies/create', [MkCompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [MkCompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}', [MkCompanyController::class, 'show'])->name('companies.show');
    Route::patch('/companies/{company}', [MkCompanyController::class, 'update'])->name('companies.update');
    Route::post('/companies/{company}/users', [MkCompanyController::class, 'storeUser'])->name('companies.users.store');
    Route::patch('/companies/{company}/users/{companyUser}', [MkCompanyController::class, 'updateUser'])
        ->name('companies.users.update');
    Route::post('/companies/{company}/users/{companyUser}/reset-password', [MkCompanyController::class, 'resetUserPassword'])
        ->name('companies.users.reset-password');
});

require __DIR__.'/settings.php';
