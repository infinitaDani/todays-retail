<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantOperationalScope;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantManagement
{
    public function __construct(private readonly TenantOperationalScope $scopes)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $scope = $this->scopes->for($request->user(), $request->attributes->get('tenantAccount'));

        if ($scope['role'] !== TenantOperationalScope::MANAGEMENT) {
            throw new AuthorizationException('Solo Management puede administrar el equipo.');
        }

        $request->attributes->set('tenantOperationalScope', $scope);

        return $next($request);
    }
}
