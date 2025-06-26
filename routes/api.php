<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


$app_name = env('APP_NAME', '');

Route::get("/test", function (Request $request) {
    return response()->json([
        'message' => 'Test successful',
        'timestamp' => now(),
    ]);
});

Route::prefix($app_name)->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    // Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/validate', [AuthController::class, 'validate'])->name('validate');
});
