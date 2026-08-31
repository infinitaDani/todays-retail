<x-layouts.tenant title="Planificar horarios" subtitle="Matriz semanal de asignaciones">
    <div class="d-flex gap-2 mb-3">
        <a class="btn btn-light" href="{{ route('operations.schedule') }}">Calendario</a>
        <a class="btn btn-light" href="{{ route('operations.schedule.report') }}">Reporte de jornada</a>
        @if (app(\App\Tenancy\TenantOperationalScope::class)->canManageTenant($scope))
            <a class="btn btn-light" href="{{ route('operations.schedule.settings') }}">Configuración</a>
        @endif
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="btn-group">
            <a class="btn btn-outline-secondary {{ $viewMode === 'week' ? 'active' : '' }}" href="{{ route('operations.planner', array_filter(['week' => $week->format('Y-m-d'), 'branch_id' => $branchId, 'view' => 'week'])) }}">Semana</a>
            <a class="btn btn-outline-secondary {{ $viewMode === 'fortnight' ? 'active' : '' }}" href="{{ route('operations.planner', array_filter(['week' => $week->format('Y-m-d'), 'branch_id' => $branchId, 'view' => 'fortnight'])) }}">Quincena</a>
            <a class="btn btn-outline-secondary {{ $viewMode === 'month' ? 'active' : '' }}" href="{{ route('operations.planner', array_filter(['week' => $week->format('Y-m-d'), 'branch_id' => $branchId, 'view' => 'month'])) }}">Mes</a>
        </div>

        @if ($schedulePeriod)
            <span class="badge badge-soft-primary">{{ ['draft' => 'Borrador', 'pending' => 'Pendiente de aprobación', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'][$schedulePeriod->status] }}</span>
        @endif
    </div>

    <form class="tr-card mb-3" method="GET">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label" for="planner-month">Período</label>
                <select class="form-select" id="planner-month" name="month">
                    @for ($offset = -12; $offset <= 12; $offset++)
                        @php($candidate = now()->startOfMonth()->addMonths($offset))
                        <option value="{{ $candidate->format('Y-m') }}" @selected($candidate->format('Y-m') === $month->format('Y-m'))>{{ $candidate->translatedFormat('F Y') }}</option>
                    @endfor
                </select>
            </div>

            @if (! $scope['branch_id'])
                <div class="col-md-4">
                    <select class="form-select" name="branch_id">
                        <option value="">Selecciona sucursal</option>

                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-2">
                <button class="btn btn-primary">Abrir período</button>
            </div>
        </div>
    </form>

    @if (! $schedulePeriod && $branchId)
        <div class="tr-card mb-3">
            <p class="mb-3">No existe horario para este período.</p>
            <form method="POST" action="{{ route('operations.planner.periods.store') }}">
                @csrf
                <input name="branch_id" type="hidden" value="{{ $branchId }}">
                <input name="month_key" type="hidden" value="{{ $month->format('Y-m') }}">
                <button class="btn btn-primary">Crear horario</button>
            </form>
        </div>
    @endif

    @if ($schedulePeriod && \Carbon\Carbon::createFromFormat('Y-m', $schedulePeriod->month_key)->endOfMonth()->lt(now()->startOfDay()))
        <div class="tr-card mb-3">
            <p>Este horario pertenece a un período histórico. Solo puede modificarse mediante Modo ajustes y autorización aprobada.</p>
            <form method="POST" action="{{ route('operations.schedule-periods.change-requests.store', $schedulePeriod) }}">
                @csrf
                <label class="form-label" for="historical-reason">Motivo de la solicitud</label>
                <textarea class="form-control" id="historical-reason" name="reason" required></textarea>
                <button class="btn btn-outline-primary mt-2">Solicitar autorización para modificar</button>
            </form>
        </div>
    @endif

    @if ($schedulePeriod)
    @if ($viewMode === 'fortnight')
        <div class="btn-group mb-3">
            <a class="btn btn-outline-secondary {{ request('fortnight', '1') === '1' ? 'active' : '' }}" href="{{ route('operations.planner', ['month' => $month->format('Y-m'), 'branch_id' => $branchId, 'view' => 'fortnight', 'fortnight' => 1]) }}">Primera quincena</a>
            <a class="btn btn-outline-secondary {{ request('fortnight') === '2' ? 'active' : '' }}" href="{{ route('operations.planner', ['month' => $month->format('Y-m'), 'branch_id' => $branchId, 'view' => 'fortnight', 'fortnight' => 2]) }}">Segunda quincena</a>
        </div>
    @endif
    @if ($viewMode === 'week')
        @php($previousWeek = $week->copy()->subWeek())
        @php($nextWeek = $week->copy()->addWeek())
        <div class="d-flex gap-2 mb-3">
            @if ($previousWeek->endOfWeek()->gte($month->copy()->startOfMonth()))
                <a class="btn btn-outline-secondary" href="{{ route('operations.planner', ['month' => $month->format('Y-m'), 'branch_id' => $branchId, 'view' => 'week', 'week' => $previousWeek->format('Y-m-d')]) }}">← Semana anterior</a>
            @endif
            @if ($nextWeek->startOfWeek()->lte($month->copy()->endOfMonth()))
                <a class="btn btn-outline-secondary" href="{{ route('operations.planner', ['month' => $month->format('Y-m'), 'branch_id' => $branchId, 'view' => 'week', 'week' => $nextWeek->format('Y-m-d')]) }}">Semana siguiente →</a>
            @endif
        </div>
    @endif

    @if ($schedulePeriod->status === 'draft')
        <form class="mb-3" method="POST" action="{{ route('operations.planner.submit') }}">
            @csrf
            <input name="branch_id" type="hidden" value="{{ $branchId }}">
            <input name="month_key" type="hidden" value="{{ $month->format('Y-m') }}">
            <button class="btn btn-outline-primary">Enviar a aprobación</button>
        </form>
    @elseif ($schedulePeriod->status === 'pending')
        <div class="alert alert-warning">Pendiente de aprobación. El horario está bloqueado.</div>
        @if (app(\App\Tenancy\TenantOperationalScope::class)->canManageTenant($scope))
            <form method="POST" action="{{ route('operations.schedule-periods.review', $schedulePeriod) }}">
                @csrf
                @method('PATCH')
                <input name="status" type="hidden" value="approved">
                <button class="btn btn-success">Aprobar</button>
            </form>
            <form class="mt-2" method="POST" action="{{ route('operations.schedule-periods.review', $schedulePeriod) }}">
                @csrf
                @method('PATCH')
                <input name="status" type="hidden" value="rejected">
                <input class="form-control" name="review_comment" placeholder="Motivo de rechazo" required>
                <button class="btn btn-outline-danger mt-2">Rechazar</button>
            </form>
        @endif
    @elseif ($schedulePeriod->status === 'approved')
        <div class="alert alert-success">Aprobado. Usa Modo ajustes para registrar cambios auditados.</div>
        <button class="btn btn-outline-primary" type="button" data-adjustment-mode>Hacer ajustes</button>
    @endif

    <form id="planner-form" method="POST" action="{{ route('operations.planner.save') }}">
        @csrf
        <input name="week" type="hidden" value="{{ $month->format('Y-m-d') }}">
        <input name="branch_id" type="hidden" value="{{ $branchId }}">
        <input name="view" type="hidden" value="{{ $viewMode }}">
        <input name="fortnight" type="hidden" value="{{ $fortnight }}">
        <input name="adjustment_mode" type="hidden" value="0" data-adjustment-mode-input>
        <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
            <small class="text-warning d-none" data-unsaved>Cambios sin guardar</small>
            <button class="btn btn-primary" @disabled($schedulePeriod->status !== 'draft')>Guardar cambios</button>
        </div>
    @foreach ($weeks as $week)
        <section class="mb-4">
            <h2 class="h6">Semana del {{ $week->format('d/m/Y') }}</h2>
        <div class="tr-card p-0 overflow-auto">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        @for ($day = 0; $day < 7; $day++)
                            <th>{{ $week->copy()->addDays($day)->translatedFormat('D d') }}</th>
                        @endfor
                        <th>Horas asignadas</th>
                        <th>Libres</th>
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
                                $assignment = $assignments->get($profile->core_user_id.'-'.$week->copy()->addDays($day)->toDateString());
                                $shift = $assignment?->shift;
                            @endphp

                            @php
                                $start = $shift && ! $shift->is_day_off ? \Carbon\Carbon::parse($shift->start_time) : null;
                                $end = $shift && ! $shift->is_day_off ? \Carbon\Carbon::parse($shift->end_time) : null;
                                $end = $end && $end->lte($start) ? $end->addDay() : $end;
                                $hours += $start ? $start->diffInMinutes($end) / 60 : 0;
                                $offs += $shift?->is_day_off ? 1 : 0;
                            @endphp
                        @endfor

                        <tr>
                            <td>{{ $users->get($profile->core_user_id)?->name ?? $profile->first_name }}</td>

                            @for ($day = 0; $day < 7; $day++)
                                @php
                                    $date = $week->copy()->addDays($day)->toDateString();
                                    $assignment = $assignments->get($profile->core_user_id.'-'.$date);
                                    $absent = $absences->contains(fn ($absence) => $absence->core_user_id === $profile->core_user_id && $absence->starts_at?->toDateString() <= $date && $absence->ends_at?->toDateString() >= $date);
                                @endphp

                                @php($editableDate = $date >= $activeStart->toDateString() && $date <= $activeEnd->toDateString() && substr($date, 0, 7) === $month->format('Y-m'))
                                <td class="{{ ! $editableDate ? 'text-muted bg-light' : '' }}">
                                    @if ($editableDate)
                                    <select class="form-select form-select-sm" name="cells[{{ $profile->core_user_id }}:{{ $date }}]" data-user="{{ $profile->core_user_id }}" data-day="{{ $day }}" @disabled($absent || ! in_array($schedulePeriod->status, ['draft', 'approved'], true))>
                                        <option value="">Sin asignar</option>

                                        @foreach ($shifts as $shift)
                                            @php
                                                $start = $shift->is_day_off ? null : \Carbon\Carbon::parse($shift->start_time);
                                                $end = $shift->is_day_off ? null : \Carbon\Carbon::parse($shift->end_time);
                                                $end = $end && $end->lte($start) ? $end->addDay() : $end;
                                                $shiftHours = $start ? $start->diffInMinutes($end) / 60 : 0;
                                            @endphp
                                            <option value="{{ $shift->id }}" data-hours="{{ $shiftHours }}" data-day-off="{{ $shift->is_day_off ? '1' : '0' }}" @selected($assignment?->shift_id === $shift->id)>{{ $shift->is_day_off ? $shift->name : $shift->name.' · '.substr($shift->start_time, 0, 5).'–'.substr($shift->end_time, 0, 5) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($schedulePeriod->status === 'approved')
                                        <input class="form-control form-control-sm mt-1 d-none" data-adjustment-reason name="adjustment_reasons[{{ $profile->core_user_id }}:{{ $date }}]" placeholder="Motivo del ajuste">
                                    @endif

                                    @else
                                        <small>Fuera del período</small>
                                    @endif

                                    @if ($absent)
                                        <small class="text-warning">Ausencia aprobada</small>
                                    @endif
                                </td>
                            @endfor

                            <td data-hours-total data-expected-hours="{{ $settings->expected_hours_per_week }}">{{ number_format($hours, 1) }} / {{ $settings->expected_hours_per_week }} h</td>
                            <td data-offs-total data-required-offs="{{ $settings->required_days_off_per_week }}">{{ $offs }} / {{ $settings->required_days_off_per_week }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-copy-week title="Duplicar semana anterior" aria-label="Duplicar semana anterior">Duplicar semana anterior</button>
    </form>
        </section>
    @endforeach
    </form>
    @endif

    @push('page-scripts')
        <script>
            (() => {
                const form = document.querySelector('#planner-form');
                const unsaved = document.querySelector('[data-unsaved]');

                if (!form) return;

                const adjustmentButton = document.querySelector('[data-adjustment-mode]');
                if (adjustmentButton) {
                    form.querySelectorAll('select[name^="cells"]').forEach((select) => {
                        if (!select.closest('td').querySelector('.text-warning')) select.disabled = true;
                    });
                    adjustmentButton.addEventListener('click', () => {
                        form.querySelector('[data-adjustment-mode-input]').value = '1';
                        form.querySelectorAll('select[name^="cells"]').forEach((select) => {
                            if (!select.closest('td').querySelector('.text-warning')) select.disabled = false;
                        });
                        adjustmentButton.classList.add('d-none');
                    });
                }

                const recalculate = (section) => {
                    let hours = 0;
                    let offs = 0;
                    section.querySelectorAll('select[name^="cells"]').forEach((select) => {
                        const option = select.options[select.selectedIndex];
                        hours += Number(option?.dataset.hours || 0);
                        offs += option?.dataset.dayOff === '1' ? 1 : 0;
                    });
                    const hoursTarget = section.querySelector('[data-hours-total]');
                    const offsTarget = section.querySelector('[data-offs-total]');
                    hoursTarget.textContent = `${hours.toFixed(1)} / ${hoursTarget.dataset.expectedHours} h`;
                    offsTarget.textContent = `${offs} / ${offsTarget.dataset.requiredOffs}`;
                };

                form.addEventListener('change', (event) => {
                    unsaved.classList.remove('d-none');
                    recalculate(event.target.closest('section'));
                    const reason = event.target.closest('td')?.querySelector('[data-adjustment-reason]');
                    if (reason) reason.classList.remove('d-none');
                });

                document.querySelectorAll('[data-copy-week]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const section = button.closest('section');
                        const previous = section.previousElementSibling;
                        if (!previous || previous.tagName !== 'SECTION') return;
                        section.querySelectorAll('select[name^="cells"]').forEach((select) => {
                            const source = previous.querySelector(`select[data-user="${select.dataset.user}"][data-day="${select.dataset.day}"]`);
                            if (source && !select.disabled) select.value = source.value;
                        });
                        recalculate(section);
                        unsaved.classList.remove('d-none');
                    });
                });

                window.addEventListener('beforeunload', (event) => {
                    if (!unsaved.classList.contains('d-none')) {
                        event.preventDefault();
                        event.returnValue = '';
                    }
                });
            })();
        </script>
    @endpush
</x-layouts.tenant>
