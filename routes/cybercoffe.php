<?php

use App\Http\Controllers\CyberCoffe\AttendantController;
use App\Http\Controllers\CyberCoffe\AuthController;
use App\Http\Controllers\CyberCoffe\CustomerController;
use App\Http\Controllers\CyberCoffe\CustomerPortalController;
use App\Http\Controllers\CyberCoffe\PaymentController;
use App\Http\Controllers\CyberCoffe\PlanController;
use App\Http\Controllers\CyberCoffe\PrintJobController;
use App\Http\Controllers\CyberCoffe\ProductController;
use App\Http\Controllers\CyberCoffe\ReportController;
use App\Http\Controllers\CyberCoffe\SessionItemController;
use App\Http\Controllers\CyberCoffe\StationAgentController;
use App\Http\Controllers\CyberCoffe\StationController;
use App\Http\Controllers\CyberCoffe\StationSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CyberCoffe API v1
|--------------------------------------------------------------------------
|
| Loaded under the "api/cybercoffe/v1" prefix with the "cybercoffe." route
| name prefix. Every authenticated route is scoped to the token's store.
|
*/

Route::middleware('throttle:10,1')->prefix('auth')->name('auth.')->group(function () {
    Route::post('attendants/login', [AuthController::class, 'attendantLogin'])->name('attendants.login');
    Route::post('customers/login', [AuthController::class, 'customerLogin'])->name('customers.login');
    Route::post('customers/register', [AuthController::class, 'customerRegister'])->name('customers.register');
});

Route::middleware(['cybercoffe.auth:attendant', 'throttle:240,1'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('stations', [StationController::class, 'index'])->name('stations.index');
    Route::get('stations/{station}', [StationController::class, 'show'])->whereNumber('station')->name('stations.show');
    Route::patch('stations/{station}/status', [StationController::class, 'updateStatus'])->whereNumber('station')->name('stations.status');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');
    Route::get('customers/{customer}/credits', [CustomerController::class, 'credits'])->whereNumber('customer')->name('customers.credits');
    Route::post('customers/{customer}/credits', [CustomerController::class, 'purchaseCredits'])->whereNumber('customer')->name('customers.credits.purchase');

    Route::get('sessions', [StationSessionController::class, 'index'])->name('sessions.index');
    Route::post('sessions', [StationSessionController::class, 'checkIn'])->name('sessions.check-in');
    Route::get('sessions/{session}', [StationSessionController::class, 'show'])->whereNumber('session')->name('sessions.show');
    Route::post('sessions/{session}/extend', [StationSessionController::class, 'extend'])->whereNumber('session')->name('sessions.extend');
    Route::post('sessions/{session}/checkout', [StationSessionController::class, 'checkOut'])->whereNumber('session')->name('sessions.check-out');
    Route::post('sessions/{session}/payments', [StationSessionController::class, 'pay'])->whereNumber('session')->name('sessions.payments.store');
    Route::post('sessions/{session}/items', [SessionItemController::class, 'store'])->whereNumber('session')->name('sessions.items.store');
    Route::delete('sessions/{session}/items/{charge}', [SessionItemController::class, 'destroy'])->whereNumber(['session', 'charge'])->name('sessions.items.destroy');
    Route::post('sessions/{session}/prints', [SessionItemController::class, 'storePrints'])->whereNumber('session')->name('sessions.prints.store');
    Route::post('sessions/{session}/tickets', [PrintJobController::class, 'storeForSession'])->whereNumber('session')->name('sessions.tickets.store');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/tickets', [PrintJobController::class, 'storeForPayment'])->whereNumber('payment')->name('payments.tickets.store');

    Route::get('print-jobs', [PrintJobController::class, 'index'])->name('print-jobs.index');
    Route::post('print-jobs/{printJob}/printed', [PrintJobController::class, 'markPrinted'])->whereNumber('printJob')->name('print-jobs.printed');

    Route::middleware('cybercoffe.manager')->group(function () {
        Route::post('stations', [StationController::class, 'store'])->name('stations.store');
        Route::patch('stations/{station}', [StationController::class, 'update'])->whereNumber('station')->name('stations.update');
        Route::post('stations/{station}/agent-token', [StationController::class, 'issueAgentToken'])->whereNumber('station')->name('stations.agent-token');

        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::patch('plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan')->name('plans.update');

        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');

        Route::get('attendants', [AttendantController::class, 'index'])->name('attendants.index');
        Route::post('attendants', [AttendantController::class, 'store'])->name('attendants.store');
        Route::patch('attendants/{attendant}', [AttendantController::class, 'update'])->whereNumber('attendant')->name('attendants.update');

        Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
        Route::get('reports/occupancy', [ReportController::class, 'occupancy'])->name('reports.occupancy');
        Route::get('reports/consumption', [ReportController::class, 'consumption'])->name('reports.consumption');
    });
});

Route::middleware(['cybercoffe.auth:customer', 'throttle:120,1'])->prefix('me')->name('me.')->group(function () {
    Route::get('/', [CustomerPortalController::class, 'me'])->name('show');
    Route::get('credits', [CustomerPortalController::class, 'credits'])->name('credits');
    Route::get('sessions', [CustomerPortalController::class, 'sessions'])->name('sessions');
    Route::get('session', [CustomerPortalController::class, 'currentSession'])->name('session');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['cybercoffe.auth:station', 'throttle:240,1'])->prefix('agent')->name('agent.')->group(function () {
    Route::post('heartbeat', [StationAgentController::class, 'heartbeat'])->name('heartbeat');
    Route::get('session', [StationAgentController::class, 'session'])->name('session');
    Route::post('prints', [StationAgentController::class, 'prints'])->name('prints');
});
