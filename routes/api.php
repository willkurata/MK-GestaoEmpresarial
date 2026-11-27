<?php

use App\Http\Controllers\Api\PublicPropertyController;
use App\Http\Controllers\Totem\AgentController;
use App\Http\Controllers\Totem\PixWebhookController;
use App\Http\Controllers\Totem\PrintOrderController;
use App\Http\Controllers\Totem\TotemDisplayController;
use Illuminate\Support\Facades\Route;

/*
| Public, read-only endpoints consumed by the MK Negócios site.
| Only properties that are not drafts are ever returned.
*/
Route::middleware('throttle:120,1')->name('api.')->group(function () {
    Route::get('/imoveis', [PublicPropertyController::class, 'index'])->name('properties.index');
    Route::get('/imoveis/{slug}', [PublicPropertyController::class, 'show'])->name('properties.show');
});

/*
|--------------------------------------------------------------------------
| Totem de Impressão Autônoma — API v1 (WIL-46)
|--------------------------------------------------------------------------
*/

Route::prefix('totem/v1')->name('totem.')->group(function () {
    Route::middleware('throttle:totem-display')->group(function () {
        Route::get('/totems/{totem}/display', TotemDisplayController::class)->name('display');
    });

    Route::middleware('throttle:totem-customer')->group(function () {
        Route::post('/totems/{totem}/sessions', [PrintOrderController::class, 'store'])->name('sessions.store');
        Route::get('/orders/{order}', [PrintOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/file', [PrintOrderController::class, 'uploadFile'])
            ->middleware('throttle:totem-upload')
            ->name('orders.file');
        Route::patch('/orders/{order}', [PrintOrderController::class, 'update'])->name('orders.update');
        Route::post('/orders/{order}/checkout', [PrintOrderController::class, 'checkout'])->name('orders.checkout');
        Route::post('/orders/{order}/cancel', [PrintOrderController::class, 'cancel'])->name('orders.cancel');
    });

    Route::post('/webhooks/pix', PixWebhookController::class)
        ->middleware('throttle:totem-webhook')
        ->name('webhooks.pix');

    Route::prefix('agent')->name('agent.')->group(function () {
        Route::post('/pair', [AgentController::class, 'pair'])
            ->middleware('throttle:totem-pair')
            ->name('pair');

        Route::middleware(['auth:totem', 'throttle:totem-agent'])->group(function () {
            Route::post('/token/rotate', [AgentController::class, 'rotateToken'])->name('token.rotate');
            Route::post('/heartbeat', [AgentController::class, 'heartbeat'])->name('heartbeat');
            Route::post('/jobs/claim', [AgentController::class, 'claim'])->name('jobs.claim');
            Route::get('/jobs/{order}/file', [AgentController::class, 'file'])->name('jobs.file');
            Route::post('/jobs/{order}/progress', [AgentController::class, 'progress'])->name('jobs.progress');
            Route::post('/jobs/{order}/complete', [AgentController::class, 'complete'])->name('jobs.complete');
            Route::post('/jobs/{order}/fail', [AgentController::class, 'fail'])->name('jobs.fail');
        });
    });
});
