<?php

use App\Http\Controllers\AiGatewayController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ArchivePhysicalLocationController;
use App\Http\Controllers\ArchiveStorageRuleController;
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
        Route::post('/token-login', [AuthController::class, 'tokenLogin'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::prefix('archives')->group(function () {
        Route::middleware('ai.tool')->group(function () {
            Route::get('/internal', [ArchiveController::class, 'index'])->middleware('throttle:60,1');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/', [ArchiveController::class, 'index'])->middleware('throttle:60,1');
            Route::post('/', [ArchiveController::class, 'store'])->middleware('throttle:30,1');

            Route::get('/without-location', [ArchiveController::class, 'archivesWithoutLocation'])->middleware('throttle:60,1');
            Route::get('/physical-locations', [ArchivePhysicalLocationController::class, 'index'])->middleware('throttle:60,1');

            Route::get('/{id}', [ArchiveController::class, 'show']);
            Route::put('/{id}', [ArchiveController::class, 'update'])->middleware('throttle:30,1');
            Route::delete('/{id}', [ArchiveController::class, 'destroy'])->middleware(['throttle:10,1', 'admin']);
            Route::get('/{id}/preview', [ArchiveController::class, 'preview'])->middleware('throttle:60,1');
            Route::get('/{id}/download', [ArchiveController::class, 'download'])->middleware('throttle:30,1');

            Route::get('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'show']);
            Route::post('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'store'])->middleware('throttle:30,1');
            Route::put('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'update'])->middleware('throttle:30,1');
            Route::delete('/{id}/physical-locations', [ArchivePhysicalLocationController::class, 'destroy'])->middleware(['throttle:10,1', 'admin']);

            Route::get('/retention/ready', [ArchiveController::class, 'readyForDestruction']);
            Route::patch('/{id}/retention/decide', [ArchiveController::class, 'decideRetention'])->middleware('throttle:10,1');

        });
    });

    Route::prefix('events')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [EventController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/pending-uploads', [EventController::class, 'getPendingUploads'])->middleware('throttle:60,1');
        Route::get('/{id}', [EventController::class, 'show']);
        Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])->group(function () {
            Route::post('/', [EventController::class, 'store']);
            Route::put('/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
        });
    });

    Route::prefix('categories')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/{id}', [CategoryController::class, 'show']);

        Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{id}', [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
        });
    });

    Route::prefix('subcategories')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [SubcategoryController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/{id}', [SubcategoryController::class, 'show']);
        Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])->group(function () {
            Route::post('/', [SubcategoryController::class, 'store']);
            Route::put('/{id}', [SubcategoryController::class, 'update']);
            Route::delete('/{id}', [SubcategoryController::class, 'destroy']);
        });
    });

    Route::prefix('cabinets')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [CabinetController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/{id}', [CabinetController::class, 'show']);
        Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])->group(function () {
            Route::post('/', [CabinetController::class, 'store']);
            Route::put('/{id}', [CabinetController::class, 'update']);
            Route::delete('/{id}', [CabinetController::class, 'destroy']);
        });
    });

    Route::prefix('racks')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [RackController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/{id}', [RackController::class, 'show']);

        Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])->group(function () {
            Route::post('/', [RackController::class, 'store']);
            Route::put('/{id}', [RackController::class, 'update']);
            Route::delete('/{id}', [RackController::class, 'destroy']);
        });
    });

    Route::prefix('users')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/me', [UserController::class, 'updateMe'])->middleware('throttle:10,1');
        });

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::get('/', [UserController::class, 'index'])->middleware('throttle:60,1');
        });

        Route::middleware(['auth:sanctum', 'admin', 'throttle:30,1'])->group(function () {
            Route::put('/{id}', [UserController::class, 'update']);
            Route::post('/', [UserController::class, 'store']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::put('/{id}/reset-password', [UserController::class, 'reset_password']);
        });
    });

    Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::post('chat/ask', [AiGatewayController::class, 'askChat']);
        Route::post('ai/chat/ask', [AiGatewayController::class, 'askChat']);
    });

    Route::prefix('ai/tools')->middleware(['ai.tool', 'throttle:30,1'])->group(function () {
        Route::post('/archives/search', [AiGatewayController::class, 'searchArchivesTool']);
    });

    Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/teachers-without-archives', [DashboardController::class, 'teachersWithoutArchives']);
    });

    Route::middleware(['admin', 'auth:sanctum'])->group(function () {
        Route::prefix('archive-storage-rules')->group(function () {
            Route::get('/', [ArchiveStorageRuleController::class, 'index'])->middleware('throttle:60,1');
        });
    });

    Route::middleware(['admin', 'auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::prefix('archive-storage-rules')->group(function () {
            Route::post('/', [ArchiveStorageRuleController::class, 'store']);
            Route::get('/{id}', [ArchiveStorageRuleController::class, 'show']);
            Route::patch('/{id}', [ArchiveStorageRuleController::class, 'update']);
            Route::delete('/{id}', [ArchiveStorageRuleController::class, 'destroy']);
        });
    });

});
