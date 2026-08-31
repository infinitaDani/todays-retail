<x-layouts.tenant
    title="Planificar horarios"
    subtitle="Matriz mensual de asignaciones"
>
    <div class="d-flex gap-2 mb-3">
        <a
            class="btn btn-light"
            href="{{ route('operations.schedule') }}"
        >
            Calendario
        </a>

        <a
            class="btn btn-light"
            href="{{ route('operations.schedule.report') }}"
        >
            Reporte de jornada
        </a>

        @if (app(\App\Tenancy\TenantOperationalScope::class)->canManageTenant($scope))
            <a
                class="btn btn-light"
                href="{{ route('operations.schedule.settings') }}"
            >
                Configuración
            </a>
        @endif
    </div>

    <form
        class="tr-card mb-3"
        method="GET"
        action="{{ route('operations.planner') }}"
    >
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label
                    class="form-label"
                    for="planner-month"
                >
                    Período
                </label>

                <select
                    class="form-select"
                    id="planner-month"
                    name="month"
                >
                    @for ($offset = -12; $offset <= 12; $offset++)
                        @php
                            $candidate = now()
                                ->startOfMonth()
                                ->addMonths($offset);
                        @endphp

                        <option
                            value="{{ $candidate->format('Y-m') }}"
                            @selected(
                                $candidate->format('Y-m')
                                === $month->format('Y-m')
                            )
                        >
                            {{ $candidate->translatedFormat('F Y') }}
                        </option>
                    @endfor
                </select>
            </div>

            @if (! $scope['branch_id'])
                <div class="col-md-4">
                    <label
                        class="form-label"
                        for="planner-branch"
                    >
                        Sucursal
                    </label>

                    <select
                        class="form-select"
                        id="planner-branch"
                        name="branch_id"
                        required
                    >
                        <option value="">
                            Selecciona sucursal
                        </option>

                        @foreach ($branches as $branch)
                            <option
                                value="{{ $branch->id }}"
                                @selected($branchId === $branch->id)
                            >
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-2">
                <button
                    class="btn btn-primary w-100"
                    type="submit"
                    name="opened"
                    value="1"
                >
                    Abrir período
                </button>
            </div>
        </div>
    </form>

    @if ($opened && ! $schedulePeriod && $branchId)
        <div class="tr-card mb-3">
            <p class="mb-3">
                No existe un horario para
                <strong>{{ $month->translatedFormat('F Y') }}</strong>.
            </p>

            <form
                method="POST"
                action="{{ route('operations.planner.periods.store') }}"
            >
                @csrf

                <input
                    name="branch_id"
                    type="hidden"
                    value="{{ $branchId }}"
                >

                <input
                    name="month_key"
                    type="hidden"
                    value="{{ $month->format('Y-m') }}"
                >

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Crear horario
                </button>
            </form>
        </div>
    @endif

    @if ($opened && $schedulePeriod)
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1">
                    {{ $month->translatedFormat('F Y') }}
                </h2>

                <small class="text-muted">
                    Horario mensual
                </small>
            </div>

            <span class="badge badge-soft-primary">
                {{
                    [
                        'draft' => 'Borrador',
                        'pending' => 'Pendiente de aprobación',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ][$schedulePeriod->status] ?? $schedulePeriod->status
                }}
            </span>
        </div>

        @if (
            \Carbon\Carbon::createFromFormat(
                'Y-m',
                $schedulePeriod->month_key
            )->endOfMonth()->lt(now()->startOfDay())
        )
            <div class="tr-card mb-3">
                <p class="mb-3">
                    Este horario pertenece a un período histórico.
                    Para modificarlo necesitas una autorización aprobada
                    y utilizar el modo de ajustes.
                </p>

                <form
                    method="POST"
                    action="{{ route(
                        'operations.schedule-periods.change-requests.store',
                        $schedulePeriod
                    ) }}"
                >
                    @csrf

                    <label
                        class="form-label"
                        for="historical-reason"
                    >
                        Motivo de la solicitud
                    </label>

                    <textarea
                        class="form-control"
                        id="historical-reason"
                        name="reason"
                        required
                    ></textarea>

                    <button
                        class="btn btn-outline-primary mt-2"
                        type="submit"
                    >
                        Solicitar autorización para modificar
                    </button>
                </form>
            </div>
        @endif

        @if ($schedulePeriod->status === 'pending')
            <div class="alert alert-warning">
                El horario está pendiente de aprobación y se encuentra
                bloqueado.
            </div>

            @if (app(\App\Tenancy\TenantOperationalScope::class)->canManageTenant($scope))
                <div class="tr-card mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <form
                            method="POST"
                            action="{{ route(
                                'operations.schedule-periods.review',
                                $schedulePeriod
                            ) }}"
                        >
                            @csrf
                            @method('PATCH')

                            <input
                                name="status"
                                type="hidden"
                                value="approved"
                            >

                            <button
                                class="btn btn-success"
                                type="submit"
                            >
                                Aprobar
                            </button>
                        </form>
                    </div>

                    <form
                        class="mt-3"
                        method="POST"
                        action="{{ route(
                            'operations.schedule-periods.review',
                            $schedulePeriod
                        ) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <input
                            name="status"
                            type="hidden"
                            value="rejected"
                        >

                        <label
                            class="form-label"
                            for="review-comment"
                        >
                            Motivo de rechazo
                        </label>

                        <input
                            class="form-control"
                            id="review-comment"
                            name="review_comment"
                            required
                        >

                        <button
                            class="btn btn-outline-danger mt-2"
                            type="submit"
                        >
                            Rechazar
                        </button>
                    </form>
                </div>
            @endif
        @elseif ($schedulePeriod->status === 'approved')
            <div class="alert alert-success">
                El horario está aprobado y congelado.
                Usa el modo de ajustes para registrar cambios auditados.
            </div>

            <div class="mb-3">
                <button
                    class="btn btn-outline-primary"
                    type="button"
                    data-adjustment-mode
                >
                    Hacer ajustes
                </button>
            </div>
        @elseif ($schedulePeriod->status === 'rejected')
            <div class="alert alert-danger">
                Este horario fue rechazado.
            </div>
        @endif

        @if ($schedulePeriod->status === 'draft')
            <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                <small
                    class="text-warning d-none"
                    data-unsaved
                >
                    Cambios sin guardar
                </small>

                <button
                    class="btn btn-primary"
                    type="submit"
                    form="planner-form"
                    title="Guardar cambios"
                    aria-label="Guardar cambios"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                </button>

                <form
                    class="m-0"
                    method="POST"
                    action="{{ route('operations.planner.submit') }}"
                >
                    @csrf

                    <input
                        name="branch_id"
                        type="hidden"
                        value="{{ $branchId }}"
                    >

                    <input
                        name="month_key"
                        type="hidden"
                        value="{{ $month->format('Y-m') }}"
                    >

                    <button
                        class="btn btn-outline-primary"
                        type="submit"
                        title="Enviar a aprobación"
                        aria-label="Enviar a aprobación"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </form>
            </div>
        @endif

        <form
            id="planner-form"
            method="POST"
            action="{{ route('operations.planner.save') }}"
        >
            @csrf

            <input
                name="branch_id"
                type="hidden"
                value="{{ $branchId }}"
            >

            <input
                name="month_key"
                type="hidden"
                value="{{ $month->format('Y-m') }}"
            >

            <input
                name="adjustment_mode"
                type="hidden"
                value="0"
                data-adjustment-mode-input
            >

            @foreach ($weeks as $week)
                <section class="mb-4">
                    <h2 class="h6 mb-2">
                        Semana del {{ $week->format('d/m/Y') }}
                    </h2>

                    <div class="tr-card p-0 overflow-auto">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>
                                        Colaborador
                                    </th>

                                    @for ($day = 0; $day < 7; $day++)
                                        @php
                                            $headerDate = $week
                                                ->copy()
                                                ->addDays($day);
                                        @endphp

                                        <th>
                                            {{ $headerDate->translatedFormat('D d') }}
                                        </th>
                                    @endfor

                                    <th>
                                        Horas asignadas
                                    </th>

                                    <th>
                                        Libres
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($profiles as $profile)
                                    @php
                                        $hours = 0;
                                        $offs = 0;
                                    @endphp

                                    @for ($day = 0; $day < 7; $day++)
                                        @php
                                            $calculationDate = $week
                                                ->copy()
                                                ->addDays($day);

                                            $calculationDateString = $calculationDate
                                                ->toDateString();

                                            $calculationIsInsideMonth = $calculationDate
                                                ->format('Y-m') === $month->format('Y-m');

                                            $calculationAssignment = $calculationIsInsideMonth
                                                ? $assignments->get(
                                                    $profile->core_user_id
                                                    . '-'
                                                    . $calculationDateString
                                                )
                                                : null;

                                            $calculationShift = $calculationAssignment?->shift;
                                        @endphp

                                        @if ($calculationShift)
                                            @if ($calculationShift->is_day_off)
                                                @php
                                                    $offs++;
                                                @endphp
                                            @else
                                                @php
                                                    $calculationStart = \Carbon\Carbon::parse(
                                                        $calculationShift->start_time
                                                    );

                                                    $calculationEnd = \Carbon\Carbon::parse(
                                                        $calculationShift->end_time
                                                    );

                                                    if ($calculationEnd->lte($calculationStart)) {
                                                        $calculationEnd->addDay();
                                                    }

                                                    $hours += $calculationStart
                                                        ->diffInMinutes($calculationEnd) / 60;
                                                @endphp
                                            @endif
                                        @endif
                                    @endfor

                                    <tr>
                                        <td>
                                            {{
                                                $users->get(
                                                    $profile->core_user_id
                                                )?->name
                                                ?? $profile->first_name
                                            }}

                                            @if ($profile->branch_id !== $branchId)
                                                <small class="d-block text-muted mt-1">
                                                    Sucursal principal:
                                                    {{ $profile->branch?->name ?? 'Sin sucursal asignada' }}
                                                </small>
                                            @endif
                                        </td>

                                        @for ($day = 0; $day < 7; $day++)
                                            @php
                                                $cellDate = $week
                                                    ->copy()
                                                    ->addDays($day);

                                                $date = $cellDate
                                                    ->toDateString();

                                                $editableDate = $cellDate
                                                    ->format('Y-m')
                                                    === $month->format('Y-m');

                                                $assignment = $editableDate
                                                    ? $assignments->get(
                                                        $profile->core_user_id
                                                        . '-'
                                                        . $date
                                                    )
                                                    : null;

                                                $absent = $editableDate
                                                    && $absences->contains(
                                                        fn ($absence) =>
                                                            $absence->core_user_id
                                                                === $profile->core_user_id
                                                            && $absence->starts_at?->toDateString()
                                                                <= $date
                                                            && $absence->ends_at?->toDateString()
                                                                >= $date
                                                    );
                                            @endphp

                                            <td
                                                class="{{ ! $editableDate ? 'text-muted bg-light' : '' }}"
                                            >
                                                @if ($editableDate)
                                                    <select
                                                        class="form-select form-select-sm"
                                                        name="cells[{{ $profile->core_user_id }}:{{ $date }}]"
                                                        data-user="{{ $profile->core_user_id }}"
                                                        data-date="{{ $date }}"
                                                        @disabled(
                                                            $absent
                                                            || ! in_array(
                                                                $schedulePeriod->status,
                                                                ['draft', 'approved'],
                                                                true
                                                            )
                                                        )
                                                    >
                                                        <option value="">
                                                            Sin asignar
                                                        </option>

                                                        @foreach ($shifts as $shift)
                                                            @php
                                                                $shiftHours = 0;

                                                                if (! $shift->is_day_off) {
                                                                    $shiftStart = \Carbon\Carbon::parse(
                                                                        $shift->start_time
                                                                    );

                                                                    $shiftEnd = \Carbon\Carbon::parse(
                                                                        $shift->end_time
                                                                    );

                                                                    if ($shiftEnd->lte($shiftStart)) {
                                                                        $shiftEnd->addDay();
                                                                    }

                                                                    $shiftHours = $shiftStart
                                                                        ->diffInMinutes($shiftEnd) / 60;
                                                                }
                                                            @endphp

                                                            <option
                                                                value="{{ $shift->id }}"
                                                                data-hours="{{ $shiftHours }}"
                                                                data-day-off="{{ $shift->is_day_off ? '1' : '0' }}"
                                                                @selected(
                                                                    $assignment?->shift_id
                                                                    === $shift->id
                                                                )
                                                            >
                                                                @if ($shift->is_day_off)
                                                                    {{ $shift->name }}
                                                                @else
                                                                    {{
                                                                        $shift->name
                                                                        . ' · '
                                                                        . substr($shift->start_time, 0, 5)
                                                                        . '–'
                                                                        . substr($shift->end_time, 0, 5)
                                                                    }}
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </select>

                                                    @if ($schedulePeriod->status === 'approved')
                                                        <input
                                                            class="form-control form-control-sm mt-1 d-none"
                                                            data-adjustment-reason
                                                            name="adjustment_reasons[{{ $profile->core_user_id }}:{{ $date }}]"
                                                            placeholder="Motivo del ajuste"
                                                        >
                                                    @endif
                                                @else
                                                    <small>
                                                        Fuera del período
                                                    </small>
                                                @endif

                                                @if ($absent)
                                                    <small class="text-warning d-block mt-1">
                                                        Ausencia aprobada
                                                    </small>
                                                @endif
                                            </td>
                                        @endfor

                                        <td
                                            data-hours-total
                                            data-expected-hours="{{ $settings->expected_hours_per_week }}"
                                        >
                                            {{ number_format($hours, 1) }}
                                            /
                                            {{ $settings->expected_hours_per_week }} h
                                        </td>

                                        <td
                                            data-offs-total
                                            data-required-offs="{{ $settings->required_days_off_per_week }}"
                                        >
                                            {{ $offs }}
                                            /
                                            {{ $settings->required_days_off_per_week }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </form>
    @endif

    @push('page-scripts')
        <script>
            (() => {
                const form = document.querySelector('#planner-form');

                if (!form) {
                    return;
                }

                const unsaved = document.querySelector('[data-unsaved]');
                const adjustmentButton = document.querySelector('[data-adjustment-mode]');
                const adjustmentModeInput = form.querySelector('[data-adjustment-mode-input]');

                const recalculateRow = (row) => {
                    if (!row) {
                        return;
                    }

                    let hours = 0;
                    let offs = 0;

                    row.querySelectorAll('select[name^="cells"]').forEach((select) => {
                        const option = select.options[select.selectedIndex];

                        hours += Number(option?.dataset.hours || 0);

                        if (option?.dataset.dayOff === '1') {
                            offs++;
                        }
                    });

                    const hoursTarget = row.querySelector('[data-hours-total]');
                    const offsTarget = row.querySelector('[data-offs-total]');

                    if (hoursTarget) {
                        hoursTarget.textContent =
                            `${hours.toFixed(1)} / ${hoursTarget.dataset.expectedHours} h`;
                    }

                    if (offsTarget) {
                        offsTarget.textContent =
                            `${offs} / ${offsTarget.dataset.requiredOffs}`;
                    }
                };

                if (adjustmentButton) {
                    form.querySelectorAll('select[name^="cells"]').forEach((select) => {
                        select.disabled = true;
                    });

                    adjustmentButton.addEventListener('click', () => {
                        adjustmentModeInput.value = '1';

                        form.querySelectorAll('select[name^="cells"]').forEach((select) => {
                            const cell = select.closest('td');
                            const absence = cell?.querySelector('.text-warning');

                            if (!absence) {
                                select.disabled = false;
                            }
                        });

                        adjustmentButton.classList.add('d-none');
                    });
                }

                form.addEventListener('change', (event) => {
                    const target = event.target;

                    if (!target.matches('select[name^="cells"]')) {
                        return;
                    }

                    if (unsaved) {
                        unsaved.classList.remove('d-none');
                    }

                    recalculateRow(
                        target.closest('tr')
                    );

                    const reason = target
                        .closest('td')
                        ?.querySelector('[data-adjustment-reason]');

                    if (reason) {
                        reason.classList.remove('d-none');
                        reason.required = true;
                    }
                });

                form.addEventListener('submit', () => {
                    if (unsaved) {
                        unsaved.classList.add('d-none');
                    }
                });

                window.addEventListener('beforeunload', (event) => {
                    if (
                        unsaved
                        && !unsaved.classList.contains('d-none')
                    ) {
                        event.preventDefault();
                        event.returnValue = '';
                    }
                });
            })();
        </script>
    @endpush
</x-layouts.tenant>
