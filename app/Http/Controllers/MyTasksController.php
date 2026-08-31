<?php

namespace App\Http\Controllers;

use App\Core\Accounts\Account;
use App\Core\Users\User;
use App\Modules\Operations\Models\Assignment;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\StaffProfile;
use App\Modules\Tasks\Models\TaskExecution;
use App\Services\DailyTaskService;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MyTasksController extends Controller
{
    public function index(Request $request, DailyTaskService $daily): View
    {
        $data = $request->validate(['date' => ['nullable', 'date'], 'branch_id' => ['nullable', 'integer'], 'core_user_id' => ['nullable', 'integer']]);
        $scope = $request->attributes->get('tenantOperationalScope');
        $account = $request->attributes->get('tenantAccount');
        $operationalProfile = ($scope['is_account_administrator'] ?? false) ? StaffProfile::query()->where('core_user_id', $request->user()->id)->where('status', 'active')->first() : null;
        $supervisionMode = ($scope['is_account_administrator'] ?? false) && ! $operationalProfile;
        $date = Carbon::parse($data['date'] ?? now())->toDateString();
        [$branchId, $userId] = $this->resolveFilters($data, $scope, $account, $request->user()->id);

        $assignments = Assignment::query()->with('shift')->whereDate('date', $date)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($userId, fn (Builder $query) => $query->where('core_user_id', $userId))
            ->get();
        foreach ($assignments as $assignment) { $daily->materialize($assignment); }

        $tasks = TaskExecution::query()->with(['checklistExecution', 'checklistItem.task.knowledgeArticles.versions'])->whereDate('scheduled_date', $date)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($userId, fn (Builder $query) => $query->where('core_user_id', $userId))
            ->orderBy('scheduled_start')->orderBy('scheduled_end')->get();
        $users = $this->availableUsers($account, $scope, $branchId)->keyBy('id');
        $role = $scope['role']; $canPreviewAll = ($scope['is_account_administrator'] ?? false) || $role === TenantOperationalScope::MANAGEMENT;
        $tasks->each(function (TaskExecution $task) use ($role, $canPreviewAll): void { $articles = $task->checklistItem?->task?->knowledgeArticles ?? collect(); $task->setRelation('supportArticles', $articles->filter(function ($article) use ($role, $canPreviewAll) { $version=$article->versions->firstWhere('status','published'); return $article->status==='published' && $version && ($canPreviewAll || in_array('all',$version->audience?:['all'],true) || in_array($role,$version->audience?:['all'],true)); })); });
        $timeline = $this->timeline($tasks, $assignments);
        $performance = $this->performance($tasks, now());

        return view('tenant.operations.my-tasks', [
            'date' => $date, 'tasks' => $tasks, 'scope' => $scope, 'branchId' => $branchId, 'userId' => $userId,
            'branches' => (($scope['is_account_administrator'] ?? false) || $scope['role'] === TenantOperationalScope::MANAGEMENT) ? Branch::query()->where('status', 'active')->orderBy('name')->get() : collect(),
            'users' => $users, 'timeline' => $timeline, 'performance' => $performance,
            'supervisionMode' => $supervisionMode,
            'canCompleteOwnTasks' => ! ($scope['is_account_administrator'] ?? false) || (bool) $operationalProfile,
        ]);
    }

    public function complete(Request $request, TaskExecution $execution): RedirectResponse
    {
        $scope = $request->attributes->get('tenantOperationalScope');
        if (($scope['is_account_administrator'] ?? false)) {
            $profile = StaffProfile::query()->where('core_user_id', $request->user()->id)->where('status', 'active')->first();
            if (! $profile || $execution->core_user_id !== $request->user()->id) {
                throw new AuthorizationException('Tu cuenta solo tiene acceso de supervisión; no puedes completar tareas operativas.');
            }
        }
        if ($scope['role'] === TenantOperationalScope::ADVISOR && $execution->core_user_id !== $request->user()->id) {
            throw new AuthorizationException('No puedes completar tareas de otro colaborador.');
        }
        if ($scope['branch_id'] && $execution->branch_id !== $scope['branch_id']) {
            throw new AuthorizationException('No puedes completar tareas de otra sucursal.');
        }
        if ($execution->completed_at) {
            return back()->withErrors(['task' => 'Esta tarea ya fue completada.']);
        }
        $execution->update(['completed_at' => now()]);
        return back()->with('success', 'Tarea completada.');
    }

    private function resolveFilters(array $data, array $scope, Account $account, int $currentUserId): array
    {
        if ($scope['role'] === TenantOperationalScope::ADVISOR) {
            if (! empty($data['core_user_id']) && (int) $data['core_user_id'] !== $currentUserId) {
                throw new AuthorizationException('No puedes consultar tareas de otro colaborador.');
            }
            if (! empty($data['branch_id']) && (int) $data['branch_id'] !== $scope['branch_id']) {
                throw new AuthorizationException('No puedes consultar otra sucursal.');
            }
            return [$scope['branch_id'], $currentUserId];
        }

        if ($scope['role'] === TenantOperationalScope::STORE_ADMIN && ! empty($data['branch_id']) && (int) $data['branch_id'] !== $scope['branch_id']) {
            throw new AuthorizationException('No puedes consultar otra sucursal.');
        }
        $branchId = $scope['role'] === TenantOperationalScope::STORE_ADMIN ? $scope['branch_id'] : ($data['branch_id'] ?? null);
        if ($branchId && ! Branch::query()->whereKey($branchId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'La sucursal seleccionada no está disponible.']);
        }
        $userId = $data['core_user_id'] ?? null;
        if ($userId && ! $this->availableUsers($account, $scope, $branchId)->contains('id', (int) $userId)) {
            throw new AuthorizationException('No puedes consultar este colaborador.');
        }
        return [$branchId ? (int) $branchId : null, $userId ? (int) $userId : null];
    }

    private function availableUsers(Account $account, array $scope, ?int $branchId)
    {
        $users = $account->users()->where('users.status', 'active')->orderBy('users.name')->get();
        $scopedBranch = $scope['branch_id'] ?? $branchId;
        if (! $scopedBranch) { return $users; }
        $ids = StaffProfile::query()->where('branch_id', $scopedBranch)->where('status', 'active')->pluck('core_user_id');
        return $users->whereIn('id', $ids);
    }

    private function timeline($tasks, $assignments): array
    {
        $starts = $tasks->pluck('scheduled_start')->filter()->map(fn ($time) => substr((string) $time, 0, 5));
        $ends = $tasks->pluck('scheduled_end')->filter()->map(fn ($time) => substr((string) $time, 0, 5));
        if ($starts->isEmpty() && $assignments->isNotEmpty()) {
            $starts = $assignments->pluck('shift.start_time')->filter()->map(fn ($time) => substr((string) $time, 0, 5));
            $ends = $assignments->pluck('shift.end_time')->filter()->map(fn ($time) => substr((string) $time, 0, 5));
        }
        return ['start' => $starts->sort()->first(), 'end' => $ends->sortDesc()->first()];
    }

    private function performance($tasks, Carbon $now): array
    {
        $total = $tasks->count();
        $completed = $tasks->whereNotNull('completed_at');
        $onTime = $completed->filter(fn (TaskExecution $task) => $task->status($now)->value === 'completed_on_time')->count();
        $late = $completed->filter(fn (TaskExecution $task) => $task->status($now)->value === 'completed_late')->count();
        return ['total' => $total, 'completed' => $completed->count(), 'on_time' => $onTime, 'late' => $late, 'completion_rate' => $total ? round($completed->count() * 100 / $total) : 0, 'on_time_rate' => $total ? round($onTime * 100 / $total) : 0];
    }
}
