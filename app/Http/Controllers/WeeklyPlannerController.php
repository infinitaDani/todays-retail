<?php

namespace App\Http\Controllers;

use App\Modules\Operations\Models\Assignment;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\ScheduleAdjustment;
use App\Modules\Operations\Models\SchedulePeriod;
use App\Modules\Operations\Models\SchedulePeriodChangeRequest;
use App\Modules\Operations\Models\ScheduleSetting;
use App\Modules\Operations\Models\Shift;
use App\Modules\Operations\Models\StaffProfile;
use App\Modules\Requests\Models\TenantRequest;
use App\Core\Accounts\AccountUser;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WeeklyPlannerController extends Controller
{
    public function createPeriod(
        Request $request,
        TenantOperationalScope $scopes
    ): RedirectResponse {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'month_key' => ['required', 'date_format:Y-m'],
        ]);

        $branchId = $this->branchId(
            $request,
            $scope,
            true
        );

        SchedulePeriod::firstOrCreate(
            [
                'branch_id' => $branchId,
                'month_key' => $data['month_key'],
            ],
            [
                'status' => 'draft',
                'created_by_core_user_id' => $request->user()->id,
            ]
        );

        return redirect()->route(
            'operations.planner',
            [
                'branch_id' => $branchId,
                'month' => $data['month_key'],
                'opened' => 1,
            ]
        );
    }

    public function plan(
        Request $request,
        TenantOperationalScope $scopes
    ): View {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        $branchId = $this->branchId(
            $request,
            $scope
        );

        $month = Carbon::parse(
            $request->input(
                'month',
                now()->startOfMonth()
            )
        )->startOfMonth();

        $opened = $request->boolean('opened');

        $period = null;

        if ($branchId) {
            $period = SchedulePeriod::query()
                ->where('branch_id', $branchId)
                ->where(
                    'month_key',
                    $month->format('Y-m')
                )
                ->first();
        }

        $accountUserIds = AccountUser::query()
            ->where(
                'account_id',
                $request->attributes->get('tenantAccount')->id
            )
            ->pluck('user_id');

        $profiles = StaffProfile::query()
            ->where('status', 'active')
            ->whereIn('core_user_id', $accountUserIds)
            ->when(
                $branchId,
                fn ($query) => $query->where(
                    fn ($profiles) => $profiles
                        ->where('branch_id', $branchId)
                        ->orWhere(
                            fn ($externalProfiles) => $externalProfiles
                                ->where('can_work_other_branches', true)
                                ->whereNotNull('branch_id')
                                ->where('branch_id', '!=', $branchId)
                        )
                )
            )
            ->with('branch')
            ->orderBy('first_name')
            ->get();

        $userIds = $profiles->pluck(
            'core_user_id'
        );

        $assignments = Assignment::query()
            ->with('shift')
            ->when(
                $branchId,
                fn ($query) => $query->where('branch_id', $branchId)
            )
            ->whereIn(
                'core_user_id',
                $userIds
            )
            ->whereBetween(
                'date',
                [
                    $month
                        ->copy()
                        ->startOfMonth()
                        ->toDateString(),
                    $month
                        ->copy()
                        ->endOfMonth()
                        ->toDateString(),
                ]
            )
            ->get()
            ->keyBy(
                fn ($assignment) => $assignment->core_user_id
                    . '-'
                    . $assignment->date->toDateString()
            );

        $absences = TenantRequest::query()
            ->where('status', 'approved')
            ->whereIn(
                'type',
                [
                    'vacation',
                    'permission',
                ]
            )
            ->whereIn(
                'core_user_id',
                $userIds
            )
            ->whereDate(
                'starts_at',
                '<=',
                $month->copy()->endOfMonth()
            )
            ->whereDate(
                'ends_at',
                '>=',
                $month->copy()->startOfMonth()
            )
            ->get();

        $users = $request
            ->attributes
            ->get('tenantAccount')
            ->users()
            ->whereIn(
                'users.id',
                $userIds
            )
            ->get()
            ->keyBy('id');

        $activeStart = $month
            ->copy()
            ->startOfMonth();

        $activeEnd = $month
            ->copy()
            ->endOfMonth();

        $firstWeek = $month
            ->copy()
            ->startOfMonth()
            ->startOfWeek();

        $lastWeek = $month
            ->copy()
            ->endOfMonth()
            ->endOfWeek();

        $weeks = collect();

		$currentWeek = $firstWeek->copy();

		while ($currentWeek->lte($lastWeek)) {
			$weeks->push(
				$currentWeek->copy()
			);

			$currentWeek->addWeek();
		}

        $shifts = Shift::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $settings = ScheduleSetting::firstOrCreate(
            []
        );

        return view(
            'tenant.operations.planner',
            [
                'scope' => $scope,
                'branches' => $this->branches($scope),
                'branchId' => $branchId,
                'month' => $month,
                'weeks' => $weeks,
                'activeStart' => $activeStart,
                'activeEnd' => $activeEnd,
                'schedulePeriod' => $period,
                'opened' => $opened,
                'profiles' => $profiles,
                'users' => $users,
                'assignments' => $assignments,
                'absences' => $absences,
                'shifts' => $shifts,
                'settings' => $settings,
            ]
        );
    }

    public function save(
        Request $request,
        TenantOperationalScope $scopes
    ): RedirectResponse {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'month_key' => ['required', 'date_format:Y-m'],
            'cells' => ['required', 'array'],
            'cells.*' => ['nullable', 'integer'],
        ]);

        $branchId = $this->branchId(
            $request,
            $scope,
            true
        );

        $month = Carbon::createFromFormat(
            'Y-m',
            $data['month_key']
        )->startOfMonth();

        $rangeStart = $month
            ->copy()
            ->startOfMonth();

        $rangeEnd = $month
            ->copy()
            ->endOfMonth();

        $period = SchedulePeriod::firstOrCreate(
            [
                'branch_id' => $branchId,
                'month_key' => $month->format('Y-m'),
            ],
            [
                'status' => 'draft',
                'created_by_core_user_id' => $request->user()->id,
            ]
        );

        $historicalRequest = $this->approvedHistoricalRequest(
            $period,
            $request->user()->id
        );

        if ($period->status === 'pending') {
            throw ValidationException::withMessages([
                'schedule' => 'El horario está pendiente de aprobación y no puede modificarse.',
            ]);
        }

        if (
            $period->status === 'approved'
            && ! $request->boolean('adjustment_mode')
        ) {
            throw ValidationException::withMessages([
                'schedule' => 'El horario aprobado está congelado. Usa Modo ajustes.',
            ]);
        }

        if (
            $this->isPastPeriod($period)
            && ! $historicalRequest
        ) {
            throw ValidationException::withMessages([
                'schedule' => 'Este período histórico requiere autorización aprobada para usar Modo ajustes.',
            ]);
        }

        $accountUserIds = AccountUser::query()
            ->where(
                'account_id',
                $request->attributes->get('tenantAccount')->id
            )
            ->pluck('user_id');

        $allowedUsers = StaffProfile::query()
            ->where('status', 'active')
            ->whereIn('core_user_id', $accountUserIds)
            ->where(
                fn ($profiles) => $profiles
                    ->where('branch_id', $branchId)
                    ->orWhere(
                        fn ($externalProfiles) => $externalProfiles
                            ->where('can_work_other_branches', true)
                            ->whereNotNull('branch_id')
                            ->where('branch_id', '!=', $branchId)
                    )
            )
            ->pluck(
                'core_user_id'
            );

        $staffProfiles = StaffProfile::query()
            ->whereIn('core_user_id', $allowedUsers)
            ->get()
            ->keyBy('core_user_id');

        DB::connection('tenant')->transaction(
            function () use (
                $data,
                $branchId,
                $allowedUsers,
                $period,
                $request,
                $rangeStart,
                $rangeEnd,
                $historicalRequest,
                $staffProfiles
            ): void {
                foreach (
                    $data['cells'] as $key => $shiftId
                ) {
                    [
                        $userId,
                        $date,
                    ] = explode(
                        ':',
                        $key,
                        2
                    );

                    $userId = (int) $userId;

                    if (
                        ! $allowedUsers->contains($userId)
                        || ! preg_match(
                            '/^\d{4}-\d{2}-\d{2}$/',
                            $date
                        )
                    ) {
                        throw new AuthorizationException(
                            'Celda fuera de alcance.'
                        );
                    }

                    $cellDate = Carbon::parse(
                        $date
                    );

                    if (
                        $cellDate->lt($rangeStart)
                        || $cellDate->gt($rangeEnd)
                    ) {
                        throw ValidationException::withMessages([
                            'cells' => 'No puedes modificar fechas fuera del período mensual.',
                        ]);
                    }

                    $existing = Assignment::query()
                        ->where(
                            'core_user_id',
                            $userId
                        )
                        ->whereDate(
                            'date',
                            $date
                        )
                        ->first();

                    if (
                        $existing
                        && (int) $existing->branch_id !== (int) $branchId
                    ) {
                        $profile = $staffProfiles->get($userId);
                        $name = trim(implode(' ', array_filter([
                            $profile?->first_name,
                            $profile?->last_name,
                        ]))) ?: 'Este colaborador';
                        $assignedBranch = Branch::query()
                            ->find($existing->branch_id);

                        throw ValidationException::withMessages([
                            'cells' => sprintf(
                                '%s ya está asignada en %s el %s. Elimina primero esa asignación para poder asignarla en esta sucursal.',
                                $name,
                                $assignedBranch?->name ?? 'otra sucursal',
                                Carbon::parse($date)->format('d/m/Y')
                            ),
                        ]);
                    }

                    if (
                        $period->status === 'approved'
                        && (int) ($existing?->shift_id ?? 0)
                            !== (int) $shiftId
                    ) {
                        $reason = $request->input(
                            'adjustment_reasons.' . $key
                        );

                        if (! $reason) {
                            throw ValidationException::withMessages([
                                'adjustment_reasons.' . $key
                                    => 'Cada ajuste requiere un motivo.',
                            ]);
                        }

                        ScheduleAdjustment::create([
                            'schedule_period_id'
                                => $period->id,
                            'schedule_period_change_request_id'
                                => $historicalRequest?->id,
                            'branch_id'
                                => $branchId,
                            'core_user_id'
                                => $userId,
                            'date'
                                => $date,
                            'previous_shift_id'
                                => $existing?->shift_id,
                            'new_shift_id'
                                => $shiftId ?: null,
                            'reason'
                                => $reason,
                            'comment'
                                => $request->input(
                                    'adjustment_comments.' . $key
                                ),
                            'tenant_request_id'
                                => $request->input(
                                    'adjustment_requests.' . $key
                                ),
                            'changed_by_core_user_id'
                                => $request->user()->id,
                        ]);
                    }

                    if (! $shiftId) {
                        $existing?->delete();

                        continue;
                    }

                    Shift::query()
                        ->whereKey($shiftId)
                        ->where(
                            'status',
                            'active'
                        )
                        ->firstOrFail();

                    $absence = TenantRequest::query()
                        ->where(
                            'core_user_id',
                            $userId
                        )
                        ->where(
                            'status',
                            'approved'
                        )
                        ->whereIn(
                            'type',
                            [
                                'vacation',
                                'permission',
                            ]
                        )
                        ->whereDate(
                            'starts_at',
                            '<=',
                            $date
                        )
                        ->whereDate(
                            'ends_at',
                            '>=',
                            $date
                        )
                        ->exists();

                    if ($absence) {
                        throw ValidationException::withMessages([
                            'cells' => 'No puedes asignar un turno sobre una ausencia aprobada.',
                        ]);
                    }

                    Assignment::updateOrCreate(
                        [
                            'core_user_id' => $userId,
                            'date' => $date,
                        ],
                        [
                            'branch_id' => $branchId,
                            'shift_id' => $shiftId,
                        ]
                    );
                }
            }
        );

        return redirect()
            ->route(
                'operations.planner',
                [
                    'branch_id' => $branchId,
                    'month' => $month->format('Y-m'),
                    'opened' => 1,
                ]
            )
            ->with(
                'success',
                'Horario guardado correctamente.'
            );
    }

    public function submit(
        Request $request,
        TenantOperationalScope $scopes
    ): RedirectResponse {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        $branchId = $this->branchId(
            $request,
            $scope,
            true
        );

        $data = $request->validate([
            'month_key' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        $period = SchedulePeriod::firstOrCreate(
            [
                'branch_id' => $branchId,
                'month_key' => $data['month_key'],
            ],
            [
                'status' => 'draft',
                'created_by_core_user_id' => $request->user()->id,
            ]
        );

        if ($period->status === 'approved') {
            abort(
                422,
                'El horario ya fue aprobado.'
            );
        }

        $period->update([
            'status' => 'pending',
            'submitted_by_core_user_id' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route(
                'operations.planner',
                [
                    'branch_id' => $branchId,
                    'month' => $data['month_key'],
                    'opened' => 1,
                ]
            )
            ->with(
                'success',
                'Horario enviado a aprobación.'
            );
    }

    public function review(
        Request $request,
        SchedulePeriod $schedulePeriod,
        TenantOperationalScope $scopes
    ): RedirectResponse {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        if (
            ! $scopes->canManageTenant($scope)
            || (
                $scope['branch_id']
                && $scope['branch_id'] !== $schedulePeriod->branch_id
            )
        ) {
            throw new AuthorizationException();
        }

        $data = $request->validate([
            'status' => [
                'required',
                'in:approved,rejected',
            ],
            'review_comment' => [
                'required_if:status,rejected',
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $schedulePeriod->update([
            'status' => $data['status'],
            'reviewed_by_core_user_id'
                => $request->user()->id,
            'reviewed_at' => now(),
            'review_comment'
                => $data['review_comment'] ?? null,
        ]);

        return back()->with(
            'success',
            'Revisión registrada.'
        );
    }

    public function adjustments(
        Request $request,
        TenantOperationalScope $scopes
    ): View {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        $branchId = $this->branchId(
            $request,
            $scope
        );

        $items = ScheduleAdjustment::query()
            ->when(
                $branchId,
                fn ($query) => $query->where(
                    'branch_id',
                    $branchId
                )
            )
            ->when(
                $request->filled('core_user_id'),
                fn ($query) => $query->where(
                    'core_user_id',
                    $request->integer('core_user_id')
                )
            )
            ->latest()
            ->paginate(30);

        return view(
            'tenant.operations.schedule-adjustments',
            [
                'items' => $items,
                'scope' => $scope,
            ]
        );
    }

    public function changeRequests(
        Request $request,
        TenantOperationalScope $scopes
    ): View {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        $query = SchedulePeriodChangeRequest::query()
            ->latest();

        if (! $scopes->canManageTenant($scope)) {
            $query->where(
                'requested_by_core_user_id',
                $request->user()->id
            );
        }

        return view(
            'tenant.operations.schedule-change-requests',
            [
                'items' => $query->paginate(30),
                'scope' => $scope,
            ]
        );
    }

    public function requestHistoricalChange(
        Request $request,
        SchedulePeriod $schedulePeriod,
        TenantOperationalScope $scopes
    ): RedirectResponse {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        if (
            $scope['branch_id']
            && $scope['branch_id'] !== $schedulePeriod->branch_id
        ) {
            throw new AuthorizationException();
        }

        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        SchedulePeriodChangeRequest::firstOrCreate(
            [
                'schedule_period_id'
                    => $schedulePeriod->id,
                'requested_by_core_user_id'
                    => $request->user()->id,
                'status'
                    => 'pending',
            ],
            [
                'reason'
                    => $data['reason'],
                'requested_at'
                    => now(),
            ]
        );

        return back()->with(
            'success',
            'Solicitud de autorización enviada.'
        );
    }

    public function resolveHistoricalChange(
        Request $request,
        SchedulePeriodChangeRequest $changeRequest,
        TenantOperationalScope $scopes
    ): RedirectResponse {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        if (
            ! $scopes->canManageTenant($scope)
            || $changeRequest->requested_by_core_user_id
                === $request->user()->id
        ) {
            throw new AuthorizationException();
        }

        $data = $request->validate([
            'status' => [
                'required',
                'in:approved,rejected',
            ],
            'review_comment' => [
                'required_if:status,rejected',
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $changeRequest->update([
            'status'
                => $data['status'],
            'reviewed_by_core_user_id'
                => $request->user()->id,
            'reviewed_at'
                => now(),
            'review_comment'
                => $data['review_comment'] ?? null,
        ]);

        return back()->with(
            'success',
            'Solicitud resuelta.'
        );
    }

    public function report(
        Request $request,
        TenantOperationalScope $scopes
    ): View {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        $branchId = $this->branchId(
            $request,
            $scope
        );

        $week = Carbon::parse(
            $request->input(
                'week',
                now()
            )
        )->startOfWeek();

        $settings = ScheduleSetting::firstOrCreate(
            []
        );

        $profiles = StaffProfile::query()
            ->where(
                'status',
                'active'
            )
            ->when(
                $branchId,
                fn ($query) => $query->where(
                    'branch_id',
                    $branchId
                )
            )
            ->get();

        $assignments = Assignment::query()
            ->with('shift')
            ->whereIn(
                'core_user_id',
                $profiles->pluck('core_user_id')
            )
            ->whereBetween(
                'date',
                [
                    $week,
                    $week->copy()->addDays(6),
                ]
            )
            ->get()
            ->groupBy('core_user_id');

        return view(
            'tenant.operations.schedule-report',
            [
                'scope' => $scope,
                'week' => $week,
                'settings' => $settings,
                'profiles' => $profiles,
                'assignments' => $assignments,
                'branchId' => $branchId,
            ]
        );
    }

    public function settings(
        Request $request,
        TenantOperationalScope $scopes
    ): View {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        if (! $scopes->canManageTenant($scope)) {
            throw new AuthorizationException();
        }

        return view(
            'tenant.operations.schedule-settings',
            [
                'settings'
                    => ScheduleSetting::firstOrCreate([]),
            ]
        );
    }

    public function updateSettings(
        Request $request,
        TenantOperationalScope $scopes
    ): RedirectResponse {
        $scope = $scopes->for(
            $request->user(),
            $request->attributes->get('tenantAccount')
        );

        if (! $scopes->canManageTenant($scope)) {
            throw new AuthorizationException();
        }

        $data = $request->validate([
            'expected_hours_per_week' => [
                'required',
                'numeric',
                'min:0',
            ],
            'standard_hours_per_day' => [
                'required',
                'numeric',
                'min:0',
            ],
            'required_days_off_per_week' => [
                'required',
                'integer',
                'min:0',
                'max:7',
            ],
            'warn_on_excess_hours' => [
                'nullable',
                'boolean',
            ],
        ]);

        ScheduleSetting::firstOrCreate([])
            ->update($data);

        return back()->with(
            'success',
            'Configuración guardada.'
        );
    }

    private function branches(
        array $scope
    ) {
        return Branch::query()
            ->where(
                'status',
                'active'
            )
            ->when(
                $scope['branch_id'],
                fn ($query) => $query->whereKey(
                    $scope['branch_id']
                )
            )
            ->orderBy('name')
            ->get();
    }

    private function branchId(
        Request $request,
        array $scope,
        bool $required = false
    ): ?int {
        if ($scope['branch_id']) {
            return $scope['branch_id'];
        }

        $id = $request->integer(
            'branch_id'
        );

        if ($required && ! $id) {
            throw ValidationException::withMessages([
                'branch_id'
                    => 'Selecciona una sucursal.',
            ]);
        }

        if (
            $id
            && ! Branch::query()
                ->whereKey($id)
                ->where(
                    'status',
                    'active'
                )
                ->exists()
        ) {
            throw new AuthorizationException(
                'Sucursal no disponible.'
            );
        }

        return $id ?: null;
    }

    private function isPastPeriod(
        SchedulePeriod $period
    ): bool {
        return Carbon::createFromFormat(
            'Y-m',
            $period->month_key
        )
            ->endOfMonth()
            ->lt(
                now()->startOfDay()
            );
    }

    private function approvedHistoricalRequest(
        SchedulePeriod $period,
        int $userId
    ): ?SchedulePeriodChangeRequest {
        return SchedulePeriodChangeRequest::query()
            ->where(
                'schedule_period_id',
                $period->id
            )
            ->where(
                'requested_by_core_user_id',
                $userId
            )
            ->where(
                'status',
                'approved'
            )
            ->latest(
                'reviewed_at'
            )
            ->first();
    }
}
