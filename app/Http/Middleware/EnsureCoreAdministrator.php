<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoreAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = strtolower((string) $request->user()?->email);

        abort_unless(in_array($email, config('core_admin.emails', []), true), 403);

        return $next($request);
    }
}
