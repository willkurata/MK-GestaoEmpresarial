<?php

use App\Http\Controllers\Api\PublicPropertyController;
use Illuminate\Support\Facades\Route;

/*
| Public, read-only endpoints consumed by the MK Negócios site.
| Only properties that are not drafts are ever returned.
*/
Route::middleware('throttle:120,1')->name('api.')->group(function () {
    Route::get('/imoveis', [PublicPropertyController::class, 'index'])->name('properties.index');
    Route::get('/imoveis/{slug}', [PublicPropertyController::class, 'show'])->name('properties.show');
});
