<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Core\AccountSelectionController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\CoreAdmin\AccountController as CoreAdminAccountController;
use App\Http\Controllers\CoreAdmin\AccountMembershipController;
use App\Http\Controllers\CoreAdmin\RoleController as CoreAdminRoleController;
use App\Http\Controllers\CoreAdmin\UserController as CoreAdminUserController;
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
        ->middleware(['active.account', 'tenant'])
        ->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::prefix('core-admin')->as('admin.')->middleware(['auth', 'core.admin'])->group(function () {
    Route::patch('accounts/{account}/status', [CoreAdminAccountController::class, 'toggleStatus'])
        ->name('accounts.status');
    Route::resource('accounts', CoreAdminAccountController::class)->except('destroy');

    Route::post('accounts/{account}/memberships', [AccountMembershipController::class, 'store'])
        ->name('accounts.memberships.store');
    Route::patch('accounts/{account}/memberships/{membership}', [AccountMembershipController::class, 'update'])
        ->name('accounts.memberships.update');
    Route::delete('accounts/{account}/memberships/{membership}', [AccountMembershipController::class, 'destroy'])
        ->name('accounts.memberships.destroy');

    Route::patch('users/{user}/status', [CoreAdminUserController::class, 'toggleStatus'])
        ->name('users.status');
    Route::resource('users', CoreAdminUserController::class)->except(['show', 'destroy']);
    Route::resource('roles', CoreAdminRoleController::class)->except(['show', 'destroy']);
});
