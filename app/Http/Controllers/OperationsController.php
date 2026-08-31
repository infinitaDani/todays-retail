<?php

namespace App\Http\Controllers;

use App\Core\Accounts\Account;
use App\Modules\Operations\Models\Assignment;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\Shift;
use App\Modules\Operations\Models\StaffProfile;
use App\Tenancy\TenantOperationalScope;
use App\Tenancy\AuthorizedCoreUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    public function branches(Request $request): View { $query = Branch::query()->withCount(['assignments', 'staffProfiles'])->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $nested) => $nested->where('name', 'like', '%'.$request->string('search').'%')->orWhere('code', 'like', '%'.$request->string('search').'%')))->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status'))); return view('tenant.operations.branches', ['branches' => $query->orderBy('name')->paginate(10)->withQueryString(), 'summary' => ['total' => Branch::count(), 'active' => Branch::where('status', 'active')->count(), 'inactive' => Branch::where('status', 'inactive')->count(), 'assigned_staff' => StaffProfile::whereNotNull('branch_id')->count()]]); }
    public function storeBranch(Request $request): RedirectResponse { Branch::create($request->validate(['name' => 'required|max:150', 'code' => 'nullable|max:50', 'status' => 'required|in:active,inactive'])); return back(); }
    public function shifts(Request $request): View { $query = Shift::query()->withCount(['assignments', 'checklists'])->when($request->filled('search'), fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('search').'%'))->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status'))); return view('tenant.operations.shifts', ['shifts' => $query->orderBy('name')->paginate(10)->withQueryString(), 'summary' => ['total' => Shift::count(), 'active' => Shift::where('status', 'active')->count(), 'inactive' => Shift::where('status', 'inactive')->count(), 'assigned' => Assignment::count()]]); }
    public function storeShift(Request $request): RedirectResponse { Shift::create($request->validate(['name' => 'required|max:100', 'start_time' => 'required', 'end_time' => 'required', 'status' => 'required|in:active,inactive'])); return back(); }

    public function schedule(Request $request): View
    {
        $scope = $request->attributes->get('tenantOperationalScope');
        return view('tenant.operations.schedule', ['branches' => $this->availableBranches($scope)->get(), 'shifts' => Shift::query()->where('status', 'active')->orderBy('name')->get(), 'users' => $this->availableUsers($request->attributes->get('tenantAccount'), $scope), 'scope' => $scope]);
    }

    public function scheduleEvents(Request $request): JsonResponse
    {
        $data = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after:start'], 'branch_id' => ['nullable', 'integer'], 'core_user_id' => ['nullable', 'integer'], 'shift_id' => ['nullable', 'integer']]);
        $start = Carbon::parse($data['start'])->startOfDay(); $end = Carbon::parse($data['end'])->startOfDay();
        if ($start->diffInDays($end) > 93) abort(422, 'El rango del calendario es demasiado amplio.');
        $scope = $request->attributes->get('tenantOperationalScope'); $account = $request->attributes->get('tenantAccount'); $branchId = $this->filteredBranchId($data['branch_id'] ?? null, $scope);
        if (! empty($data['core_user_id'])) $this->ensureTargetUser($account, (int) $data['core_user_id'], $branchId, $scope);
        if (! empty($data['shift_id']) && ! Shift::query()->whereKey($data['shift_id'])->exists()) abort(422, 'El turno seleccionado no existe.');
        $assignments = Assignment::query()->with(['branch', 'shift.checklists.items.task.knowledgeArticles.versions'])->where('date', '>=', $start->toDateString())->where('date', '<', $end->toDateString())->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->when($data['core_user_id'] ?? null, fn (Builder $query, $userId) => $query->where('core_user_id', $userId))->when($data['shift_id'] ?? null, fn (Builder $query, $shiftId) => $query->where('shift_id', $shiftId))->get();
        $users = $account->users()->whereIn('users.id', $assignments->pluck('core_user_id')->unique())->get()->keyBy('id');
        return response()->json($assignments->map(function (Assignment $assignment) use ($users, $scope): array {
            $user = $users->get($assignment->core_user_id);
            $shift = $assignment->shift;

            return [
                'id' => (string) $assignment->id,
                'title' => $user?->name ?? 'Colaborador',
                'start' => $assignment->date->toDateString(),
                'allDay' => true,
                'classNames' => ['tr-schedule-event'],
                'extendedProps' => [
                    'core_user_id' => $assignment->core_user_id,
                    'user_name' => $user?->name ?? 'Colaborador',
                    'branch_id' => $assignment->branch_id,
                    'branch_name' => $assignment->branch->name,
                    'shift_id' => $assignment->shift_id,
                    'shift_name' => $shift->name,
                    'shift_hours' => substr((string) $shift->start_time, 0, 5).' → '.substr((string) $shift->end_time, 0, 5),
                    'has_support_material' => $shift->checklists->flatMap(fn ($checklist) => $checklist->items)->contains(fn ($item) => $item->task->knowledgeArticles->contains(fn ($article) => $article->status === 'published' && ($scope['is_account_administrator'] ?? false || $scope['role'] === TenantOperationalScope::MANAGEMENT || $article->versions->contains(fn ($version) => $version->status === 'published' && (in_array('all',$version->audience ?: ['all'],true) || in_array($scope['role'],$version->audience ?: ['all'],true))))),
                ],
            ];
        }));
    }

    public function storeAssignment(Request $request, AuthorizedCoreUser $authorizedUsers): JsonResponse
    {
        $data = $request->validate(['core_user_id' => ['required', 'integer'], 'branch_id' => ['nullable', 'integer'], 'shift_id' => ['required', 'integer'], 'date' => ['required', 'date']]); $scope = $request->attributes->get('tenantOperationalScope'); $account = $request->attributes->get('tenantAccount'); $branchId = $this->filteredBranchId($data['branch_id'] ?? null, $scope, true);
        $authorizedUsers->ensure($account, (int) $data['core_user_id']); $this->ensureTargetUser($account, (int) $data['core_user_id'], $branchId, $scope); $this->ensureActiveShift((int) $data['shift_id']); $this->ensureNoDuplicateAssignment((int) $data['core_user_id'], $data['date']);
        try {
            $assignment = Assignment::create(['core_user_id' => $data['core_user_id'], 'branch_id' => $branchId, 'shift_id' => $data['shift_id'], 'date' => $data['date']]);
        } catch (QueryException $exception) {
            if ($this->isAssignmentDuplicate($exception)) {
                throw ValidationException::withMessages(['date' => 'Este colaborador ya tiene un turno asignado para esta fecha.']);
            }
            throw $exception;
        }
        return response()->json(['id' => $assignment->id], 201);
    }

    public function updateAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['nullable', 'integer'], 'shift_id' => ['required', 'integer'], 'date' => ['required', 'date']]); $scope = $request->attributes->get('tenantOperationalScope'); $account = $request->attributes->get('tenantAccount'); $this->ensureAssignmentIsInScope($assignment, $scope); $branchId = $this->filteredBranchId($data['branch_id'] ?? null, $scope, true);
        $this->ensureTargetUser($account, $assignment->core_user_id, $branchId, $scope); $this->ensureActiveShift((int) $data['shift_id']); $this->ensureNoDuplicateAssignment($assignment->core_user_id, $data['date'], $assignment->id);
        try {
            $assignment->update(['branch_id' => $branchId, 'shift_id' => $data['shift_id'], 'date' => $data['date']]);
        } catch (QueryException $exception) {
            if ($this->isAssignmentDuplicate($exception)) {
                throw ValidationException::withMessages(['date' => 'Este colaborador ya tiene un turno asignado para esta fecha.']);
            }
            throw $exception;
        }
        return response()->json(['id' => $assignment->id]);
    }

    public function destroyAssignment(Request $request, Assignment $assignment): JsonResponse { $this->ensureAssignmentIsInScope($assignment, $request->attributes->get('tenantOperationalScope')); $assignment->delete(); return response()->json(status: 204); }
    private function availableBranches(array $scope): Builder { return Branch::query()->where('status', 'active')->when($scope['branch_id'], fn (Builder $query, int $branchId) => $query->whereKey($branchId))->orderBy('name'); }
    private function availableUsers(Account $account, array $scope) { $users = $account->users()->where('users.status', 'active')->orderBy('users.name')->get(); if (! $scope['branch_id']) return $users; return $users->whereIn('id', StaffProfile::query()->where('branch_id', $scope['branch_id'])->pluck('core_user_id')); }
    private function filteredBranchId(?int $requestedBranchId, array $scope, bool $required = false): ?int { if ($scope['branch_id']) return $scope['branch_id']; if ($required && ! $requestedBranchId) abort(422, 'Selecciona una sucursal.'); if ($requestedBranchId && ! Branch::query()->whereKey($requestedBranchId)->where('status', 'active')->exists()) abort(422, 'La sucursal seleccionada no está disponible.'); return $requestedBranchId; }
    private function ensureTargetUser(Account $account, int $userId, ?int $branchId, array $scope): void { $membership = $account->memberships()->with(['role', 'user'])->where('user_id', $userId)->first(); if (! $membership || $membership->user?->status !== 'active') throw new AuthorizationException('El colaborador no pertenece a la cuenta activa.'); if ($scope['branch_id'] && $branchId !== $scope['branch_id']) throw new AuthorizationException('No puedes usar otra sucursal.'); if ($branchId !== null && in_array($membership->role?->code, ['store_admin', 'advisor'], true)) { $profile = StaffProfile::query()->where('core_user_id', $userId)->first(); if (! $profile?->branch_id || $profile->branch_id !== $branchId) throw new AuthorizationException('El colaborador debe asignarse únicamente a su sucursal operativa.'); } }
    private function ensureActiveShift(int $shiftId): void { if (! Shift::query()->whereKey($shiftId)->where('status', 'active')->exists()) abort(422, 'El turno seleccionado no está disponible.'); }
    private function ensureAssignmentIsInScope(Assignment $assignment, array $scope): void { if ($scope['branch_id'] && $assignment->branch_id !== $scope['branch_id']) throw new AuthorizationException('No puedes administrar asignaciones de otra sucursal.'); }
    private function ensureNoDuplicateAssignment(int $userId, string $date, ?int $ignoreId = null): void { if (Assignment::query()->where('core_user_id', $userId)->whereDate('date', $date)->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))->exists()) throw ValidationException::withMessages(['date' => 'Este colaborador ya tiene un turno asignado para esta fecha.']); }
    private function isAssignmentDuplicate(QueryException $exception): bool { return str_contains(strtolower($exception->getMessage()), 'assignments_user_date_unique'); }
}
