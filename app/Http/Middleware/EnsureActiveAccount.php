<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $accountId = $request->session()->get('active_account_id');

        if (! $accountId) {
            return redirect()->route('accounts.select');
        }

        $account = $request->user()
            ->accounts()
            ->where('accounts.id', $accountId)
            ->where('accounts.status', 'active')
            ->first();

        if (! $account) {
            $request->session()->forget('active_account_id');

            return redirect()->route('accounts.select');
        }

        $request->attributes->set('activeAccount', $account);

        return $next($request);
    }
}
