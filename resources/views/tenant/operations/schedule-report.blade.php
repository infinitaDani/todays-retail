<x-layouts.tenant title="Reporte de jornada">
    <div class="tr-card">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr><th>Colaborador</th><th>Horas esperadas</th><th>Horas asignadas</th><th>Diferencia</th><th>Libres</th></tr>
                </thead>
                <tbody>
                    @foreach ($profiles as $profile)
                        @php
                            $items = $assignments->get($profile->core_user_id, collect());
                            $hours = $items->sum(function ($assignment) {
                                if ($assignment->shift->is_day_off) {
                                    return 0;
                                }
                                $start = \Carbon\Carbon::parse($assignment->shift->start_time);
                                $end = \Carbon\Carbon::parse($assignment->shift->end_time);
                                if ($end->lte($start)) {
                                    $end->addDay();
                                }
                                return $start->diffInMinutes($end) / 60;
                            });
                            $offs = $items->where('shift.is_day_off', true)->count();
                        @endphp
                        <tr><td>{{ $profile->first_name }} {{ $profile->last_name }}</td><td>{{ $settings->expected_hours_per_week }}</td><td>{{ $hours }}</td><td>{{ $hours - $settings->expected_hours_per_week }}</td><td>{{ $offs }} / {{ $settings->required_days_off_per_week }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
