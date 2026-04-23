<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ArchivePhysicalLocationController;
use App\Http\Controllers\AiGatewayController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\SubcategoryController;
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
            Route::get('/without-location', [ArchiveController::class, 'archivesWithoutLocation']);
            Route::get('/physical-locations', [ArchivePhysicalLocationController::class, 'index']);
            Route::get('/{id}', [ArchiveController::class, 'show']);
            Route::put('/{id}', [ArchiveController::class, 'update']);
            Route::delete('/{id}', [ArchiveController::class, 'destroy']);
            Route::get('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'show']);
            Route::post('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'store']);
            Route::put('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'update']);
            Route::delete('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'destroy']);
        });
    });

    Route::prefix('events')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/{id}', [EventController::class, 'show']);

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::post('/', [EventController::class, 'store']);
            Route::put('/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
        });
    });

    Route::prefix('categories')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{id}', [CategoryController::class, 'show']);

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{id}', [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
        });
    });

    Route::prefix('subcategories')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [SubcategoryController::class, 'index']);
        Route::get('/{id}', [SubcategoryController::class, 'show']);
        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::post('/', [SubcategoryController::class, 'store']);
            Route::put('/{id}', [SubcategoryController::class, 'update']);
            Route::delete('/{id}', [SubcategoryController::class, 'destroy']);
        });
    });

    Route::prefix('cabinets')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [CabinetController::class, 'index']);
        Route::get('/{id}', [CabinetController::class, 'show']);
        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::post('/', [CabinetController::class, 'store']);
            Route::put('/{id}', [CabinetController::class, 'update']);
            Route::delete('/{id}', [CabinetController::class, 'destroy']);
        });
    });

    Route::prefix('racks')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [RackController::class, 'index']);
        Route::get('/{id}', [RackController::class, 'show']);

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::post('/', [RackController::class, 'store']);
            Route::put('/{id}', [RackController::class, 'update']);
            Route::delete('/{id}', [RackController::class, 'destroy']);
        });
    });

    Route::prefix('users')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/me', [UserController::class, 'updateMe']);
        });

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::put('/{id}', [UserController::class, 'update']);
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::put('/{id}/reset-password', [UserController::class, 'reset_password']);
        });
    });

    Route::prefix('ai')->middleware('auth:sanctum')->group(function () {
        Route::get('/health', [AiGatewayController::class, 'health']);
        Route::post('/chat/ask', [AiGatewayController::class, 'ask']);
        Route::post('/ocr/extract', [AiGatewayController::class, 'extractOcr']);
        Route::post('/pdf/extract-native', [AiGatewayController::class, 'extractPdfNative']);
    });

    Route::prefix('dashboard')->middleware('auth:sanctum')->group(function() {
        Route::get('/', [DashboardController::class, 'index']);
    });
});
