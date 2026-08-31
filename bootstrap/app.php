<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureCoreAdministrator;
use App\Http\Middleware\EnsureScheduleAdministrator;
use App\Http\Middleware\EnsureTenantManagement;
use App\Http\Middleware\EnsureTenantOperational;
use App\Http\Middleware\InitializeTenant;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        app_path('Console/Commands'),
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'active.account' => EnsureActiveAccount::class,
            'core.admin' => EnsureCoreAdministrator::class,
            'schedule.admin' => EnsureScheduleAdministrator::class,
            'tenant.management' => EnsureTenantManagement::class,
            'tenant.operational' => EnsureTenantOperational::class,
            'tenant' => InitializeTenant::class,
        ]);
		$middleware->prependToPriorityList(
			before: SubstituteBindings::class,
			prepend: [
				EnsureActiveAccount::class,
				InitializeTenant::class,
			],
		);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
