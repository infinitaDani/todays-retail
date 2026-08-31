<?php
namespace App\Http\Middleware;
use App\Tenancy\TenantOperationalScope; use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class EnsureTenantOperational { public function __construct(private readonly TenantOperationalScope $scopes) {} public function handle(Request $request, Closure $next): Response { $request->attributes->set('tenantOperationalScope', $this->scopes->for($request->user(), $request->attributes->get('tenantAccount'))); return $next($request); } }
