<?php

namespace App\Tenancy;

use App\Core\Accounts\Account;
use App\Tenancy\Exceptions\TenantNotResolvedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class TenantResolver
{
    public function resolve(Request $request): Account
    {
        $user = $request->user();
        $accountId = $request->session()->get('active_account_id');

        if (! $user || ! $accountId) {
            throw new TenantNotResolvedException('No active tenant account is available.');
        }

        $account = $user->accounts()
            ->where('accounts.id', $accountId)
            ->where('accounts.status', 'active')
            ->first();

        if (! $account) {
            throw new AuthorizationException('The active account is not available to this user.');
        }

        return $account;
    }
}
