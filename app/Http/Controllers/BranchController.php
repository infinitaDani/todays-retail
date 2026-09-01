<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\StaffProfile;
use App\Modules\Products\Services\WarehouseProvisioningService;
use App\Modules\Tasks\Models\TaskExecution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $query = Branch::query()
            ->withCount(['assignments', 'staffProfiles'])
            ->when(
                $request->filled('search'),
                function (Builder $query) use ($request): void {
                    $search = $request->string('search')->toString();

                    $query->where(function (Builder $nested) use ($search): void {
                        $nested
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where(
                    'status',
                    $request->string('status')->toString(),
                ),
            );

        return view('tenant.operations.branches', [
            'branches' => $query
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'summary' => [
                'total' => Branch::count(),
                'active' => Branch::where('status', 'active')->count(),
                'inactive' => Branch::where('status', 'inactive')->count(),
                'assigned_staff' => StaffProfile::whereNotNull('branch_id')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('tenant.operations.branch-form');
    }

    public function store(
        StoreBranchRequest $request,
        WarehouseProvisioningService $warehouses,
    ): RedirectResponse {
        $branch = DB::connection('tenant')->transaction(
            function () use ($request, $warehouses): Branch {
                $branch = Branch::create($request->validated());
                $warehouses->ensurePrimaryWarehouse($branch);

                return $branch;
            },
        );

        return redirect()
            ->route('operations.branches.show', $branch)
            ->with('success', 'Sucursal creada correctamente.');
    }

    public function show(Branch $branch): View
    {
        return view('tenant.operations.branch-show', [
            'branch' => $branch,
            'inUse' => $this->inUse($branch),
        ]);
    }

    public function edit(Branch $branch): View
    {
        return view('tenant.operations.branch-form', compact('branch'));
    }

    public function update(
        StoreBranchRequest $request,
        Branch $branch,
    ): RedirectResponse {
        $branch->update($request->validated());

        return redirect()
            ->route('operations.branches.show', $branch)
            ->with('success', 'Sucursal actualizada correctamente.');
    }

    public function toggle(Branch $branch): RedirectResponse
    {
        $branch->update([
            'status' => $branch->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Estado de sucursal actualizado.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        if ($this->inUse($branch)) {
            return back()->withErrors([
                'branch' => 'Esta sucursal tiene colaboradores, asignaciones o historial. Solo puede desactivarse.',
            ]);
        }

        $branch->delete();

        return redirect()
            ->route('operations.branches')
            ->with('success', 'Sucursal eliminada.');
    }

    private function inUse(Branch $branch): bool
    {
        return $branch->staffProfiles()->exists()
            || $branch->assignments()->exists()
            || TaskExecution::query()
                ->where('branch_id', $branch->id)
                ->exists();
    }
}
