<?php

namespace App\Http\Controllers\CoreAdmin;

use App\Core\Accounts\Account;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\CoreAdmin\StoreAccountRequest;
use App\Http\Requests\CoreAdmin\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('core-admin.accounts.index', [
            'accounts' => Account::query()->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('core-admin.accounts.create');
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = Account::query()->create($request->validated());

        return redirect()->route('admin.accounts.show', $account)
            ->with('success', 'Cuenta creada correctamente.');
    }

    public function show(Account $account): View
    {
        return view('core-admin.accounts.show', [
            'account' => $account,
            'memberships' => $account->memberships()->with(['user', 'role'])->orderBy('id')->get(),
            'availableUsers' => User::query()
                ->whereDoesntHave('accounts', fn ($query) => $query->where('accounts.id', $account->id))
                ->orderBy('name')
                ->get(),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Account $account): View
    {
        return view('core-admin.accounts.edit', compact('account'));
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->validated());

        return redirect()->route('admin.accounts.show', $account)
            ->with('success', 'Cuenta actualizada correctamente.');
    }

    public function toggleStatus(Account $account): RedirectResponse
    {
        $account->update([
            'status' => $account->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Estado de cuenta actualizado.');
    }
}
