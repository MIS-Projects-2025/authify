<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', "/authify/login");

Route::get('/authify/login', [AuthController::class, 'loginForm'])->name('sso.login');
Route::post('/authify/login', [AuthController::class, 'Login']);
Route::get('/authify/logout', [AuthController::class, 'logout'])->name('sso.logout');
