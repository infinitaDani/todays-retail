<x-layouts.tenant title="Configuración de jornada">
    <div class="tr-card">
        <form method="POST" action="{{ route('operations.schedule.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Horas esperadas por semana</label><input class="form-control" name="expected_hours_per_week" value="{{ $settings->expected_hours_per_week }}"></div>
                <div class="col-md-3"><label class="form-label">Horas estándar por día</label><input class="form-control" name="standard_hours_per_day" value="{{ $settings->standard_hours_per_day }}"></div>
                <div class="col-md-3"><label class="form-label">Días libres requeridos</label><input class="form-control" name="required_days_off_per_week" value="{{ $settings->required_days_off_per_week }}"></div>
                <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" id="warn" name="warn_on_excess_hours" type="checkbox" value="1" @checked($settings->warn_on_excess_hours)><label class="form-check-label" for="warn">Advertir excesos</label></div></div>
            </div>

            <button class="btn btn-primary mt-3">Guardar</button>
        </form>
    </div>
</x-layouts.tenant>
