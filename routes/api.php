<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

$app_name = env('APP_NAME', '');

Route::get('/validate', [AuthController::class, 'validate'])->name('validate');

Route::post('/login', [AuthController::class, 'login'])->name('login');
