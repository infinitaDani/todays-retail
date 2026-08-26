<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Core\AccountSelectionController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\CoreAdmin\AccountController as CoreAdminAccountController;
use App\Http\Controllers\CoreAdmin\AccountMembershipController;
use App\Http\Controllers\CoreAdmin\RoleController as CoreAdminRoleController;
use App\Http\Controllers\CoreAdmin\UserController as CoreAdminUserController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\TasksController;
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

Route::middleware(['auth', 'active.account', 'tenant'])->group(function () {
    Route::get('operations/branches',[OperationsController::class,'branches'])->name('operations.branches'); Route::post('operations/branches',[OperationsController::class,'storeBranch'])->name('operations.branches.store');
    Route::get('operations/shifts',[OperationsController::class,'shifts'])->name('operations.shifts'); Route::post('operations/shifts',[OperationsController::class,'storeShift'])->name('operations.shifts.store');
    Route::get('operations/schedule',[OperationsController::class,'schedule'])->name('operations.schedule'); Route::post('operations/assignments',[OperationsController::class,'storeAssignment'])->name('operations.assignments.store'); Route::delete('operations/assignments/{assignment}',[OperationsController::class,'destroyAssignment'])->name('operations.assignments.destroy');
    Route::get('tasks/tasks',[TasksController::class,'tasks'])->name('tasks.index'); Route::post('tasks/tasks',[TasksController::class,'storeTask'])->name('tasks.store'); Route::get('tasks/checklists',[TasksController::class,'checklists'])->name('checklists.index'); Route::post('tasks/checklists',[TasksController::class,'storeChecklist'])->name('checklists.store'); Route::post('tasks/checklists/{checklist}/items',[TasksController::class,'storeItem'])->name('checklists.items.store');
    Route::get('knowledge/articles',[KnowledgeController::class,'articles'])->name('knowledge.articles'); Route::post('knowledge/articles',[KnowledgeController::class,'storeArticle'])->name('knowledge.articles.store'); Route::post('knowledge/articles/{article}/assignments',[KnowledgeController::class,'assign'])->name('knowledge.assignments.store'); Route::post('knowledge/assignments/{assignment}/open',[KnowledgeController::class,'open'])->name('knowledge.assignments.open'); Route::post('knowledge/assignments/{assignment}/complete',[KnowledgeController::class,'complete'])->name('knowledge.assignments.complete'); Route::post('knowledge/assignments/{assignment}/confirm',[KnowledgeController::class,'confirm'])->name('knowledge.assignments.confirm');
});
