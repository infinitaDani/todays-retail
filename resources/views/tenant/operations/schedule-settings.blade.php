<x-layouts.tenant
    title="Configuración"
    subtitle="Jornada laboral e inventario de la cuenta activa"
>
    <div class="tr-card">
        <form method="POST" action="{{ route('operations.schedule.settings.update') }}">
            @csrf
            @method('PUT')

            <section>
                <div class="mb-3">
                    <h5 class="mb-1">Jornada laboral</h5>
                    <p class="text-muted mb-0">
                        Define los valores de referencia para la planificación de horarios.
                    </p>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="expected-hours-per-week">
                            Horas esperadas por semana
                        </label>
                        <input
                            class="form-control"
                            id="expected-hours-per-week"
                            name="expected_hours_per_week"
                            type="number"
                            min="0"
                            step="0.01"
                            value="{{ old('expected_hours_per_week', $settings->expected_hours_per_week) }}"
                            required
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="standard-hours-per-day">
                            Horas estándar por día
                        </label>
                        <input
                            class="form-control"
                            id="standard-hours-per-day"
                            name="standard_hours_per_day"
                            type="number"
                            min="0"
                            step="0.01"
                            value="{{ old('standard_hours_per_day', $settings->standard_hours_per_day) }}"
                            required
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="required-days-off-per-week">
                            Días libres requeridos
                        </label>
                        <input
                            class="form-control"
                            id="required-days-off-per-week"
                            name="required_days_off_per_week"
                            type="number"
                            min="0"
                            max="7"
                            value="{{ old('required_days_off_per_week', $settings->required_days_off_per_week) }}"
                            required
                        >
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input
                                class="form-check-input"
                                id="warn-on-excess-hours"
                                name="warn_on_excess_hours"
                                type="checkbox"
                                value="1"
                                @checked(old('warn_on_excess_hours', $settings->warn_on_excess_hours))
                            >
                            <label class="form-check-label" for="warn-on-excess-hours">
                                Advertir excesos de jornada
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <hr class="my-4">

            <section>
                <div class="mb-3">
                    <h5 class="mb-1">Inventario</h5>
                    <p class="text-muted mb-0">
                        Define si la cuenta controlará existencias dentro de Today’s Retail.
                    </p>
                </div>

                <div class="form-check form-switch mb-3">
                    <input
                        class="form-check-input"
                        id="manages-inventory"
                        name="manages_inventory"
                        type="checkbox"
                        value="1"
                        @checked(old('manages_inventory', $settings->manages_inventory))
                    >
                    <label class="form-check-label" for="manages-inventory">
                        Gestiona inventario
                    </label>
                    <div class="form-text">
                        Permite controlar existencias de productos y variantes dentro de Today’s.
                    </div>
                </div>

                <div class="form-check form-switch" data-inventory-by-branch>
                    <input
                        class="form-check-input"
                        id="inventory-by-branch"
                        name="inventory_by_branch"
                        type="checkbox"
                        value="1"
                        @checked(old('inventory_by_branch', $settings->inventory_by_branch))
                    >
                    <label class="form-check-label" for="inventory-by-branch">
                        Inventario por sucursal
                    </label>
                    <div class="form-text">
                        Permite administrar el stock mediante bodegas asociadas a cada sucursal.
                    </div>
                </div>
            </section>

            <button class="btn btn-primary mt-4" type="submit">Guardar configuración</button>
        </form>
    </div>

    @push('page-scripts')
        <script>
            const managesInventory = document.getElementById('manages-inventory');
            const inventoryByBranch = document.getElementById('inventory-by-branch');
            const inventoryByBranchGroup = document.querySelector('[data-inventory-by-branch]');

            const refreshInventorySettings = () => {
                const enabled = managesInventory.checked;

                inventoryByBranch.disabled = !enabled;
                inventoryByBranchGroup.classList.toggle('opacity-50', !enabled);
            };

            managesInventory.addEventListener('change', refreshInventorySettings);
            refreshInventorySettings();
        </script>
    @endpush
</x-layouts.tenant>
