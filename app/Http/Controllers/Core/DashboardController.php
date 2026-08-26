<?php

namespace App\Http\Controllers\Core;

use App\Core\Accounts\Account;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var Account $account */
        $account = $request->attributes->get('activeAccount');

        $membership = $request->user()
            ->memberships()
            ->where('account_id', $account->id)
            ->with('role')
            ->firstOrFail();

        return view('dashboard', [
            'account' => $account,
            'membership' => $membership,
            'canSwitchAccounts' => $request->user()
                ->accounts()
                ->where('accounts.status', 'active')
                ->count() > 1,
        ]);
    }
}
