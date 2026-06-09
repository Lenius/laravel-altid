<?php

use Illuminate\Support\Facades\Route;
use Lenius\LaravelAltid\Http\Controllers\AltIdAgeVerificationController;

Route::prefix('altid/age')->group(function (): void {
    Route::post('start', [AltIdAgeVerificationController::class, 'start']);
    Route::post('direct-post/{transactionId}', [AltIdAgeVerificationController::class, 'directPost']);
    Route::get('{transactionId}/status', [AltIdAgeVerificationController::class, 'status']);
});
