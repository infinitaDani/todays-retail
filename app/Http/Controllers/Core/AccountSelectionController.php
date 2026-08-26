<?php

namespace App\Http\Controllers\Core;

use App\Core\Accounts\Account;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountSelectionController extends Controller
{
    public function create(Request $request): View
    {
        $request->session()->forget('active_account_id');

        return view('accounts.select', [
            'accounts' => $this->activeAccounts($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
        ]);

        $account = $this->activeAccounts($request)
            ->firstWhere('id', $validated['account_id']);

        abort_unless($account instanceof Account, 403);

        $request->session()->put('active_account_id', $account->id);

        return redirect()->route('dashboard');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    private function activeAccounts(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        return $request->user()
            ->accounts()
            ->where('accounts.status', 'active')
            ->get();
    }
}
