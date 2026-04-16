<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ArchivePhysicalLocationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
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
            Route::get('/{id}/physical-location', [ArchivePhysicalLocationController::class, 'show']);
            Route::post('/{id}/physical-location', [ArchivePhysicalLocationController::class, 'store']);
            Route::put('/{id}/physical-location', [ArchivePhysicalLocationController::class, 'update']);
            Route::delete('/{id}/physical-location', [ArchivePhysicalLocationController::class, 'destroy']);
        });
    });

    Route::prefix('users')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/{id}', [UserController::class, 'show']);
        });

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::put('/{id}', [UserController::class, 'update']);
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::put('/{id}/reset-password', [UserController::class, 'reset_password']);
        });
    });
});
