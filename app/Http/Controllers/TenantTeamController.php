<?php

namespace App\Http\Controllers;

use App\Core\Accounts\AccountUser;
use App\Core\Accounts\Role;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\StaffProfile;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantTeamController extends Controller
{
    public function index(Request $request, TenantOperationalScope $scopes): View
    {
        $account = $request->attributes->get('tenantAccount');
        $memberships = $account->memberships()->with(['user', 'role'])->orderBy('id')->get();
        $profiles = StaffProfile::query()->whereIn('core_user_id', $memberships->pluck('user_id'))->get()->keyBy('core_user_id');

        return view('tenant.team.index', [
            'memberships' => $memberships,
            'profiles' => $profiles,
            'roles' => Role::query()->whereIn('code', $scopes->allowedRoleCodes())->orderBy('name')->get(),
            'branches' => Branch::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AccountUser $membership, TenantOperationalScope $scopes): RedirectResponse
    {
        $account = $request->attributes->get('tenantAccount');
        if ($membership->account_id !== $account->id) {
            throw new AuthorizationException('No puedes modificar membresías de otra cuenta.');
        }

        $data = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('core.roles', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')],
        ]);
        $role = Role::query()->whereKey($data['role_id'])->whereIn('code', $scopes->allowedRoleCodes())->first();
        if (! $role) {
            abort(422, 'Solo puedes asignar roles operativos autorizados.');
        }
        if (! empty($data['branch_id']) && ! Branch::query()->whereKey($data['branch_id'])->where('status', 'active')->exists()) {
            abort(422, 'La sucursal seleccionada no está disponible.');
        }
        if (in_array($role->code, [TenantOperationalScope::STORE_ADMIN, TenantOperationalScope::ADVISOR], true) && empty($data['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Store Admin y Asesora requieren una sucursal.'])->withInput();
        }

        $membership->update(['role_id' => $role->id]);
        StaffProfile::query()->updateOrCreate(['core_user_id' => $membership->user_id], ['branch_id' => $data['branch_id'] ?? null]);

        return back()->with('success', 'Perfil operativo actualizado.');
    }
}
