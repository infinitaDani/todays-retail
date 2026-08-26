<?php

namespace App\Http\Controllers\CoreAdmin;

use App\Core\Accounts\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\CoreAdmin\StoreRoleRequest;
use App\Http\Requests\CoreAdmin\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('core-admin.roles.index', [
            'roles' => Role::query()->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('core-admin.roles.create');
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::query()->create($request->validated());

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): View
    {
        return view('core-admin.roles.edit', compact('role'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }
}
