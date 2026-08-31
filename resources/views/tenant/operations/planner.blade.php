<x-layouts.tenant title="Planificar horarios" subtitle="Matriz semanal de asignaciones">
    <div class="d-flex gap-2 mb-3">
        <a class="btn btn-light" href="{{ route('operations.schedule') }}">Calendario</a>
        <a class="btn btn-light" href="{{ route('operations.schedule.report') }}">Reporte de jornada</a>
        <a class="btn btn-light" href="{{ route('operations.schedule.settings') }}">Configuración</a>
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
                <input class="form-control" name="week" type="date" value="{{ $week->format('Y-m-d') }}">
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
                <button class="btn btn-primary">Ir a semana</button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('operations.planner.save') }}">
        @csrf
        <input name="week" type="hidden" value="{{ $week->format('Y-m-d') }}">
        <input name="branch_id" type="hidden" value="{{ $branchId }}">

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

                                <td>
                                    <select class="form-select form-select-sm" name="cells[{{ $profile->core_user_id }}:{{ $day }}]" @disabled($absent)>
                                        <option value="">Pendiente</option>

                                        @foreach ($shifts as $shift)
                                            <option value="{{ $shift->id }}" @selected($assignment?->shift_id === $shift->id)>{{ $shift->name }}</option>
                                        @endforeach
                                    </select>

                                    @if ($absent)
                                        <small class="text-warning">Ausencia aprobada</small>
                                    @endif
                                </td>
                            @endfor

                            <td class="{{ $hours > $settings->expected_hours_per_week ? 'text-warning' : '' }}">{{ number_format($hours, 1) }} / {{ $settings->expected_hours_per_week }} h</td>
                            <td class="{{ $offs < $settings->required_days_off_per_week ? 'text-warning' : '' }}">{{ $offs }} / {{ $settings->required_days_off_per_week }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button class="btn btn-primary mt-3">Guardar semana</button>
    </form>

    <form class="mt-2" method="POST" action="{{ route('operations.planner.copy') }}" onsubmit="return confirm('¿Sobrescribir la planificación destino con la semana anterior?')">
        @csrf
        <input name="week" type="hidden" value="{{ $week->format('Y-m-d') }}">
        <input name="branch_id" type="hidden" value="{{ $branchId }}">
        <button class="btn btn-outline-secondary">Duplicar semana anterior</button>
    </form>
</x-layouts.tenant>
