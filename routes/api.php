<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TranslatorController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/translate', [TranslatorController::class, 'translateAndSearch']);
// });

Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('/v1/translate', [TranslatorController::class, 'translateAndSearch']);
    });
