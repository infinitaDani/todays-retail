@php
    $editing = isset($shift);
    $isDayOff = old('is_day_off', $shift->is_day_off ?? false);
@endphp

<x-layouts.tenant title="{{ $editing ? 'Editar turno' : 'Nuevo turno' }}" subtitle="Jornada operativa">
    <div class="tr-card">
        <form method="POST" action="{{ $editing ? route('operations.shifts.update', $shift) : route('operations.shifts.store') }}">
            @csrf

            @if ($editing)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="shift-name">Nombre</label>
                    <input class="form-control" id="shift-name" name="name" value="{{ old('name', $shift->name ?? '') }}" required>
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="is-day-off" name="is_day_off" type="checkbox" value="1" @checked($isDayOff)>
                        <label class="form-check-label" for="is-day-off">Es día libre</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="shift-status">Estado</label>
                    <select class="form-select" id="shift-status" name="status">
                        <option value="active" @selected(old('status', $shift->status ?? 'active') === 'active')>Activo</option>
                        <option value="inactive" @selected(old('status', $shift->status ?? '') === 'inactive')>Inactivo</option>
                    </select>
                </div>

                <div class="col-md-4" data-shift-hours>
                    <label class="form-label" for="shift-start">Hora inicio</label>
                    <input class="form-control" id="shift-start" name="start_time" type="time" value="{{ old('start_time', isset($shift) ? substr((string) $shift->start_time, 0, 5) : '') }}">
                </div>

                <div class="col-md-4" data-shift-hours>
                    <label class="form-label" for="shift-end">Hora fin</label>
                    <input class="form-control" id="shift-end" name="end_time" type="time" value="{{ old('end_time', isset($shift) ? substr((string) $shift->end_time, 0, 5) : '') }}">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">Guardar turno</button>
                <a class="btn btn-light" href="{{ $editing ? route('operations.shifts.show', $shift) : route('operations.shifts') }}">Cancelar</a>
            </div>
        </form>
    </div>

    @push('page-scripts')
        <script>
            (() => {
                const checkbox = document.querySelector('#is-day-off');
                const hourFields = document.querySelectorAll('[data-shift-hours]');

                const refresh = () => {
                    hourFields.forEach((field) => {
                        field.hidden = checkbox.checked;
                        field.querySelector('input').required = !checkbox.checked;
                    });
                };

                checkbox.addEventListener('change', refresh);
                refresh();
            })();
        </script>
    @endpush
</x-layouts.tenant>
