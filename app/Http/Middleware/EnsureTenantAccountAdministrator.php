<?php

namespace App\Http\Middleware;

use App\Modules\Inventory\Services\InventoryAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccountAdministrator
{
    public function __construct(
        private readonly InventoryAccess $access,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $scope = $this->access->scope($request);
        $this->access->authorizeAccountAdministrator($scope);
        $request->attributes->set('tenantOperationalScope', $scope);

        return $next($request);
    }
}
