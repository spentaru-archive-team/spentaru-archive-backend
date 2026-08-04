<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Spentaru Archive API Backend is running',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});
