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
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TasksController;
use App\Http\Controllers\TenantTeamController;
use App\Http\Controllers\MyTasksController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\TenantRequestController;
use App\Http\Controllers\WeeklyPlannerController;
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
    Route::middleware('tenant.management')->group(function () {
        Route::get('products', [ProductsController::class, 'index'])->name('products.index'); Route::get('products/create', [ProductsController::class, 'create'])->name('products.create'); Route::post('products', [ProductsController::class, 'store'])->name('products.store'); Route::post('products/bulk', [ProductsController::class, 'bulk'])->name('products.bulk');
        Route::get('products/import', [ProductImportController::class, 'create'])->name('products.imports.create');
        Route::post('products/import/preview', [ProductImportController::class, 'preview'])->name('products.imports.preview');
        Route::post('products/import/{productImport}', [ProductImportController::class, 'store'])->name('products.imports.store');
        Route::get('products/import/{productImport}', [ProductImportController::class, 'show'])->name('products.imports.show');
        Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::get('products/{product}/images/{image}', [ProductImageController::class, 'show'])->name('products.images.show');
        Route::patch('products/{product}/images/{image}/primary', [ProductImageController::class, 'primary'])->name('products.images.primary');
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
        Route::get('products/settings', [ProductsController::class, 'settings'])->name('products.settings'); Route::put('products/settings', [ProductsController::class, 'updateSettings'])->name('products.settings.update');
        Route::get('products/types', [ProductTypeController::class, 'index'])->name('products.types.index');
        Route::get('products/types/create', [ProductTypeController::class, 'create'])->name('products.types.create');
        Route::post('products/types', [ProductTypeController::class, 'store'])->name('products.types.store');
        Route::get('products/types/{productType}/edit', [ProductTypeController::class, 'edit'])->name('products.types.edit');
        Route::put('products/types/{productType}', [ProductTypeController::class, 'update'])->name('products.types.update');
        Route::patch('products/types/{productType}/status', [ProductTypeController::class, 'toggle'])->name('products.types.status');
        Route::get('products/categories', [ProductsController::class, 'categories'])->name('products.categories'); Route::get('products/categories/create', [ProductsController::class, 'createCategory'])->name('products.categories.create'); Route::post('products/categories', [ProductsController::class, 'storeCategory'])->name('products.categories.store'); Route::get('products/categories/{category}/edit', [ProductsController::class, 'editCategory'])->name('products.categories.edit'); Route::put('products/categories/{category}', [ProductsController::class, 'updateCategory'])->name('products.categories.update'); Route::patch('products/categories/{category}/status', [ProductsController::class, 'toggleCategory'])->name('products.categories.status');
        Route::get('products/collections', [ProductsController::class, 'collections'])->name('products.collections'); Route::get('products/collections/create', [ProductsController::class, 'createCollection'])->name('products.collections.create'); Route::post('products/collections', [ProductsController::class, 'storeCollection'])->name('products.collections.store'); Route::get('products/collections/{collection}', [ProductsController::class, 'showCollection'])->name('products.collections.show'); Route::get('products/collections/{collection}/edit', [ProductsController::class, 'editCollection'])->name('products.collections.edit'); Route::put('products/collections/{collection}', [ProductsController::class, 'updateCollection'])->name('products.collections.update'); Route::patch('products/collections/{collection}/status', [ProductsController::class, 'toggleCollection'])->name('products.collections.status'); Route::post('products/collections/{collection}/lines', [ProductsController::class, 'storeLine'])->name('products.collections.lines.store'); Route::put('products/collections/{collection}/lines/{line}', [ProductsController::class, 'updateLine'])->name('products.collections.lines.update');
        Route::get('products/{product}', [ProductsController::class, 'show'])->name('products.show'); Route::get('products/{product}/edit', [ProductsController::class, 'edit'])->name('products.edit'); Route::put('products/{product}', [ProductsController::class, 'update'])->name('products.update'); Route::patch('products/{product}/status', [ProductsController::class, 'toggle'])->name('products.status');
        Route::delete('products/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');
        Route::get('operations/branches', [BranchController::class, 'index'])->name('operations.branches');
        Route::get('operations/branches/create', [BranchController::class, 'create'])->name('operations.branches.create');
        Route::post('operations/branches', [BranchController::class, 'store'])->name('operations.branches.store');
        Route::get('operations/branches/{branch}', [BranchController::class, 'show'])->name('operations.branches.show');
        Route::get('operations/branches/{branch}/edit', [BranchController::class, 'edit'])->name('operations.branches.edit');
        Route::put('operations/branches/{branch}', [BranchController::class, 'update'])->name('operations.branches.update');
        Route::patch('operations/branches/{branch}/status', [BranchController::class, 'toggle'])->name('operations.branches.status');
        Route::delete('operations/branches/{branch}', [BranchController::class, 'destroy'])->name('operations.branches.destroy');
        Route::get('operations/shifts', [ShiftController::class, 'index'])->name('operations.shifts');
        Route::get('operations/shifts/create', [ShiftController::class, 'create'])->name('operations.shifts.create');
        Route::post('operations/shifts', [ShiftController::class, 'store'])->name('operations.shifts.store');
        Route::get('operations/shifts/{shift}', [ShiftController::class, 'show'])->name('operations.shifts.show');
        Route::get('operations/shifts/{shift}/edit', [ShiftController::class, 'edit'])->name('operations.shifts.edit');
        Route::put('operations/shifts/{shift}', [ShiftController::class, 'update'])->name('operations.shifts.update');
        Route::patch('operations/shifts/{shift}/status', [ShiftController::class, 'toggle'])->name('operations.shifts.status');
        Route::delete('operations/shifts/{shift}', [ShiftController::class, 'destroy'])->name('operations.shifts.destroy');
        Route::get('tasks/tasks', [TasksController::class, 'tasks'])->name('tasks.index');
        Route::get('tasks/tasks/create', [TasksController::class, 'createTask'])->name('tasks.create');
        Route::post('tasks/tasks', [TasksController::class, 'storeTask'])->name('tasks.store');
        Route::get('tasks/tasks/{task}', [TasksController::class, 'showTask'])->name('tasks.show');
        Route::get('tasks/tasks/{task}/edit', [TasksController::class, 'editTask'])->name('tasks.edit');
        Route::put('tasks/tasks/{task}', [TasksController::class, 'updateTask'])->name('tasks.update');
        Route::patch('tasks/tasks/{task}/status', [TasksController::class, 'toggleTask'])->name('tasks.status');
        Route::delete('tasks/tasks/{task}', [TasksController::class, 'destroyTask'])->name('tasks.destroy');
        Route::get('tasks/checklists', [TasksController::class, 'checklists'])->name('checklists.index');
        Route::get('tasks/checklists/create', [TasksController::class, 'createChecklist'])->name('checklists.create');
        Route::post('tasks/checklists', [TasksController::class, 'storeChecklist'])->name('checklists.store');
        Route::get('tasks/checklists/{checklist}', [TasksController::class, 'showChecklist'])->name('checklists.show');
        Route::get('tasks/checklists/{checklist}/edit', [TasksController::class, 'editChecklist'])->name('checklists.edit');
        Route::put('tasks/checklists/{checklist}', [TasksController::class, 'updateChecklist'])->name('checklists.update');
        Route::patch('tasks/checklists/{checklist}/status', [TasksController::class, 'toggleChecklist'])->name('checklists.status');
        Route::delete('tasks/checklists/{checklist}', [TasksController::class, 'destroyChecklist'])->name('checklists.destroy');
        Route::patch('tasks/checklists/{checklist}/items/order', [TasksController::class, 'reorderChecklistItems'])->name('checklists.items.order');
        Route::get('knowledge/articles', [KnowledgeController::class, 'articles'])->name('knowledge.articles');
        Route::get('knowledge/categories', [KnowledgeController::class, 'categories'])->name('knowledge.categories');
        Route::get('knowledge/categories/create', [KnowledgeController::class, 'createCategory'])->name('knowledge.categories.create');
        Route::post('knowledge/categories', [KnowledgeController::class, 'storeCategory'])->name('knowledge.categories.store');
        Route::get('knowledge/categories/{category}/edit', [KnowledgeController::class, 'editCategory'])->name('knowledge.categories.edit');
        Route::put('knowledge/categories/{category}', [KnowledgeController::class, 'updateCategory'])->name('knowledge.categories.update');
        Route::delete('knowledge/categories/{category}', [KnowledgeController::class, 'destroyCategory'])->name('knowledge.categories.destroy');
        Route::get('knowledge/articles/create', [KnowledgeController::class, 'create'])->name('knowledge.articles.create');
        Route::post('knowledge/articles', [KnowledgeController::class, 'store'])->name('knowledge.articles.store');
        Route::get('knowledge/articles/{article}', [KnowledgeController::class, 'show'])->name('knowledge.articles.show');
        Route::get('knowledge/articles/{article}/edit', [KnowledgeController::class, 'edit'])->name('knowledge.articles.edit');
        Route::put('knowledge/articles/{article}', [KnowledgeController::class, 'update'])->name('knowledge.articles.update');
        Route::patch('knowledge/articles/{article}/publish', [KnowledgeController::class, 'publish'])->name('knowledge.articles.publish');
        Route::patch('knowledge/articles/{article}/deactivate', [KnowledgeController::class, 'deactivate'])->name('knowledge.articles.deactivate');
        Route::delete('knowledge/articles/{article}', [KnowledgeController::class, 'destroy'])->name('knowledge.articles.destroy');
        Route::post('knowledge/articles/{article}/assignments', [KnowledgeController::class, 'assign'])->name('knowledge.assignments.store');
        Route::post('knowledge/assignments/{assignment}/open', [KnowledgeController::class, 'open'])->name('knowledge.assignments.open');
        Route::post('knowledge/assignments/{assignment}/complete', [KnowledgeController::class, 'complete'])->name('knowledge.assignments.complete');
        Route::post('knowledge/assignments/{assignment}/confirm', [KnowledgeController::class, 'confirm'])->name('knowledge.assignments.confirm');
    });
    Route::middleware('schedule.admin')->group(function () {
        Route::get('operations/planner', [WeeklyPlannerController::class, 'plan'])->name('operations.planner');
        Route::post('operations/planner/periods', [WeeklyPlannerController::class, 'createPeriod'])->name('operations.planner.periods.store');
        Route::post('operations/planner', [WeeklyPlannerController::class, 'save'])->name('operations.planner.save');
        Route::post('operations/planner/copy', [WeeklyPlannerController::class, 'copy'])->name('operations.planner.copy');
        Route::post('operations/planner/submit', [WeeklyPlannerController::class, 'submit'])->name('operations.planner.submit');
        Route::patch('operations/schedule-periods/{schedulePeriod}/review', [WeeklyPlannerController::class, 'review'])->name('operations.schedule-periods.review');
        Route::get('operations/schedule-adjustments', [WeeklyPlannerController::class, 'adjustments'])->name('operations.schedule-adjustments');
        Route::get('operations/schedule-change-requests', [WeeklyPlannerController::class, 'changeRequests'])->name('operations.schedule-change-requests');
        Route::post('operations/schedule-periods/{schedulePeriod}/change-requests', [WeeklyPlannerController::class, 'requestHistoricalChange'])->name('operations.schedule-periods.change-requests.store');
        Route::patch('operations/schedule-change-requests/{changeRequest}', [WeeklyPlannerController::class, 'resolveHistoricalChange'])->name('operations.schedule-change-requests.resolve');
        Route::get('operations/schedule/report', [WeeklyPlannerController::class, 'report'])->name('operations.schedule.report');
        Route::get('operations/schedule/settings', [WeeklyPlannerController::class, 'settings'])->name('operations.schedule.settings');
        Route::put('operations/schedule/settings', [WeeklyPlannerController::class, 'updateSettings'])->name('operations.schedule.settings.update');
        Route::get('operations/schedule', [OperationsController::class, 'schedule'])->name('operations.schedule');
        Route::get('operations/schedule/events', [OperationsController::class, 'scheduleEvents'])->name('operations.schedule.events');
        Route::post('operations/assignments', [OperationsController::class, 'storeAssignment'])->name('operations.assignments.store');
        Route::patch('operations/assignments/{assignment}', [OperationsController::class, 'updateAssignment'])->name('operations.assignments.update');
        Route::delete('operations/assignments/{assignment}', [OperationsController::class, 'destroyAssignment'])->name('operations.assignments.destroy');
    });
    Route::middleware('tenant.operational')->group(function () {
        Route::get('requests', [TenantRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/create', [TenantRequestController::class, 'create'])->name('requests.create');
        Route::post('requests', [TenantRequestController::class, 'store'])->name('requests.store');
        Route::get('requests/{tenantRequest}', [TenantRequestController::class, 'show'])->name('requests.show');
        Route::patch('requests/{tenantRequest}/review', [TenantRequestController::class, 'review'])->name('requests.review');
        Route::get('operations/my-tasks', [MyTasksController::class, 'index'])->name('operations.my-tasks');
        Route::post('operations/my-tasks/{execution}/complete', [MyTasksController::class, 'complete'])->name('operations.my-tasks.complete');
        Route::get('knowledge', [KnowledgeController::class, 'center'])->name('knowledge.center');
        Route::get('knowledge/read/{article}', [KnowledgeController::class, 'read'])->name('knowledge.read');
        Route::post('knowledge/versions/{version}/heartbeat', [KnowledgeController::class, 'heartbeat'])->middleware('throttle:6,1')->name('knowledge.versions.heartbeat');
        Route::post('knowledge/versions/{version}/confirm', [KnowledgeController::class, 'confirmVersion'])->name('knowledge.versions.confirm');
    });
    Route::middleware('tenant.management')->group(function () {
        Route::get('team', [TenantTeamController::class, 'index'])->name('team.index');
        Route::get('team/create', [TenantTeamController::class, 'create'])->name('team.create');
        Route::post('team', [TenantTeamController::class, 'store'])->name('team.store');
        Route::get('team/{staffProfile}', [TenantTeamController::class, 'show'])->name('team.show');
        Route::get('team/{staffProfile}/edit', [TenantTeamController::class, 'edit'])->name('team.edit');
        Route::put('team/{staffProfile}', [TenantTeamController::class, 'update'])->name('team.update');
        Route::patch('team/{staffProfile}/status', [TenantTeamController::class, 'toggleStatus'])->name('team.status');
        Route::post('team/{staffProfile}/documents', [TenantTeamController::class, 'storeDocument'])->name('team.documents.store');
        Route::get('team/{staffProfile}/documents/{document}', [TenantTeamController::class, 'downloadDocument'])->name('team.documents.download');
        Route::delete('team/{staffProfile}/documents/{document}', [TenantTeamController::class, 'destroyDocument'])->name('team.documents.destroy');
    });
});
