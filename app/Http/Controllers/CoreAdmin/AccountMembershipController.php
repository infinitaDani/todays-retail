<?php

namespace App\Http\Controllers\CoreAdmin;

use App\Core\Accounts\Account;
use App\Core\Accounts\AccountUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\CoreAdmin\StoreMembershipRequest;
use App\Http\Requests\CoreAdmin\UpdateMembershipRequest;
use Illuminate\Http\RedirectResponse;

class AccountMembershipController extends Controller
{
    public function store(StoreMembershipRequest $request, Account $account): RedirectResponse
    {
        $account->users()->attach($request->integer('user_id'), [
            'role_id' => $request->integer('role_id'),
        ]);

        return back()->with('success', 'Usuario agregado a la cuenta.');
    }

    public function update(
        UpdateMembershipRequest $request,
        Account $account,
        AccountUser $membership,
    ): RedirectResponse {
        abort_unless($membership->account_id === $account->id, 404);

        $membership->update(['role_id' => $request->integer('role_id')]);

        return back()->with('success', 'Rol de membresía actualizado.');
    }

    public function destroy(Account $account, AccountUser $membership): RedirectResponse
    {
        abort_unless($membership->account_id === $account->id, 404);

        $membership->delete();

        return back()->with('success', 'Usuario quitado de la cuenta.');
    }
}
