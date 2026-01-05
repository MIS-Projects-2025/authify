<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', "/login");

Route::get('/login', [AuthController::class, 'loginForm'])->name('sso.login');
// Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::get('/logout', [AuthController::class, 'logout'])->name('sso.logout');
