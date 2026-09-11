<?php

use Illuminate\Support\Facades\Route;
use Perfocard\Flow\Http\Controllers\DefibrillationController;

Route::group([
    'prefix' => config('flow.defibrillation.prefix'),
    'as' => 'flow.defibrillations.',
    'middleware' => config('flow.defibrillation.middleware'),
], function () {
    Route::group(['prefix' => '{type}/{model}'], function () {
        Route::post('/', [DefibrillationController::class, 'store'])->name('store');
    });
});
