<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Core\AccountSelectionController;
use App\Http\Controllers\Core\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/accounts/select', [AccountSelectionController::class, 'create'])->name('accounts.select');
    Route::post('/accounts/select', [AccountSelectionController::class, 'store'])->name('accounts.select.store');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('active.account')
        ->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
