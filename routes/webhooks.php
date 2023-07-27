<?php

use Illuminate\Support\Facades\Route;
use Samehdoush\LaravelPayments\Http\Controllers\Gateways\PaypalController;

Route::middleware('api')->prefix('api')->group(function () {

    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::post('/paypal', [PaypalController::class, 'handleWebhook']);
        Route::get('/simulate', [PaypalController::class, 'simulateWebhookEvent']);
    });
});
