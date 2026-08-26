<?php

namespace App\Http\Middleware;

use App\Tenancy\Exceptions\TenantNotResolvedException;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantConnectionManager $connections,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $account = $this->resolver->resolve($request);
        } catch (TenantNotResolvedException|AuthorizationException) {
            $request->session()->forget('active_account_id');

            return redirect()->route('accounts.select');
        }

        $this->connections->configure($account);
        $request->attributes->set('tenantAccount', $account);

        return $next($request);
    }
}
