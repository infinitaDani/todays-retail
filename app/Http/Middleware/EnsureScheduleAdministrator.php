<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantOperationalScope;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScheduleAdministrator
{
    public function __construct(private readonly TenantOperationalScope $scopes)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $scope = $this->scopes->for($request->user(), $request->attributes->get('tenantAccount'));

        if (! $this->scopes->canAdministerSchedule($scope)) {
            throw new AuthorizationException('No tienes acceso a la administración de horarios.');
        }

        $request->attributes->set('tenantOperationalScope', $scope);

        return $next($request);
    }
}
