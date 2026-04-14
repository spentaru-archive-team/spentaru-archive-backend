<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::prefix('archives')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/', [ArchiveController::class, 'index']);
            Route::post('/', [ArchiveController::class, 'store']);
            Route::get('/{id}', [ArchiveController::class, 'show']);
            Route::put('/{id}', [ArchiveController::class, 'update']);
            Route::delete('/{id}', [ArchiveController::class, 'destroy']);
        });
    });
});
