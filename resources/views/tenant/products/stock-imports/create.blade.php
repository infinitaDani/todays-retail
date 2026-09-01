<x-layouts.tenant
    title="Importar stock"
    subtitle="Reemplaza las existencias actuales de una bodega desde Excel"
>
    @php
        $statusLabels = [
            'analyzing' => 'Analizando',
            'previewed' => 'Pendiente de confirmación',
            'processing' => 'Procesando',
            'completed' => 'Completada',
            'failed' => 'Fallida',
            'cancelled' => 'Cancelada',
        ];
        $statusColors = [
            'previewed' => 'info',
            'processing' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
        ];
        $inventoryEnabled = $settings->manages_inventory
            && $settings->inventory_by_branch;
    @endphp

    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-light" href="{{ route('products.index') }}">
            <i data-lucide="arrow-left"></i>
            Volver a Productos
        </a>
    </div>

    @if (! $settings->manages_inventory)
        <div class="alert alert-warning">
            <strong>La gestión de inventario está desactivada.</strong>
            Actívala en Configuración antes de importar stock.
        </div>
    @elseif (! $settings->inventory_by_branch)
        <div class="alert alert-warning">
            <strong>El inventario por sucursal está desactivado.</strong>
            Para importar stock, habilita Inventario por sucursal en Configuración.
        </div>
    @endif

    @if ($inventoryEnabled)
        <div class="row g-3">
            <div class="col-xl-7">
                <div class="tr-card">
                    <h5 class="mb-2">Seleccionar destino y archivo</h5>
                    <p class="text-muted">
                        El valor Stock del Excel reemplazará la existencia actual en la bodega.
                        No se crearán productos ni variantes.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('products.stock-imports.preview') }}"
                        enctype="multipart/form-data"
                    >
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="branch_id">Sucursal</label>
                                <select
                                    class="form-select @error('branch_id') is-invalid @enderror"
                                    id="branch_id"
                                    name="branch_id"
                                    required
                                >
                                    <option value="">Selecciona una sucursal</option>
                                    @foreach ($branches as $branch)
                                        <option
                                            value="{{ $branch->id }}"
                                            @selected((int) old('branch_id') === $branch->id)
                                        >
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('branch_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="warehouse_id">Bodega</label>
                                <select
                                    class="form-select @error('warehouse_id') is-invalid @enderror"
                                    id="warehouse_id"
                                    name="warehouse_id"
                                    required
                                    disabled
                                >
                                    <option value="">Selecciona una bodega</option>
                                    @foreach ($branches as $branch)
                                        @foreach ($branch->warehouses as $warehouse)
                                            <option
                                                value="{{ $warehouse->id }}"
                                                data-branch-id="{{ $branch->id }}"
                                                @selected((int) old('warehouse_id') === $warehouse->id)
                                            >
                                                {{ $branch->name }} — {{ $warehouse->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="excel">Archivo Excel</label>
                                <input
                                    class="form-control @error('excel') is-invalid @enderror"
                                    id="excel"
                                    type="file"
                                    name="excel"
                                    accept=".xlsx,.xls"
                                    required
                                >
                                @error('excel')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Formatos XLSX/XLS, máximo {{ $maxFileMegabytes }} MB.
                                    Debe contener las columnas Código y Stock.
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-primary mt-3" type="submit">
                            <i data-lucide="scan-search"></i>
                            Analizar archivo
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="tr-card h-100">
                    <h5 class="mb-3">Cómo se procesará</h5>
                    <ul class="text-muted mb-0">
                        <li>El Código se compara exactamente con el SKU de la variante.</li>
                        <li>El stock reemplaza el valor actual; nunca se suma ni se resta.</li>
                        <li>El valor cero se guarda como una existencia válida.</li>
                        <li>Los códigos repetidos en el archivo se marcan como error.</li>
                        <li>Los SKU inexistentes se reportan, pero no se crean.</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="tr-card mt-3 p-0 overflow-hidden">
        <div class="p-3 border-bottom">
            <h5 class="mb-0">Historial de importaciones de stock</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Archivo</th>
                        <th>Destino</th>
                        <th>Ejecutada por</th>
                        <th>Estado</th>
                        <th>Actualizados</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($imports as $import)
                        <tr>
                            <td>{{ $import->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $import->original_filename }}</td>
                            <td>
                                {{ $import->branch?->name ?? 'Sucursal eliminada' }}
                                —
                                {{ $import->warehouse?->name ?? 'Bodega eliminada' }}
                            </td>
                            <td>
                                {{ $userNames[$import->core_user_id] ?? 'Usuario #' . $import->core_user_id }}
                            </td>
                            <td>
                                <span class="badge badge-soft-{{ $statusColors[$import->status] ?? 'primary' }}">
                                    {{ $statusLabels[$import->status] ?? $import->status }}
                                </span>
                            </td>
                            <td>{{ $import->updated_count }}</td>
                            <td class="text-end">
                                @if ($import->status !== 'previewed' || (int) $import->core_user_id === $currentUserId)
                                    <a
                                        class="btn btn-sm btn-light"
                                        href="{{ route('products.stock-imports.show', $import) }}"
                                    >
                                        Ver
                                    </a>
                                @else
                                    <span class="text-muted small">Pendiente de otro usuario</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="listing-empty">
                                    No hay importaciones de stock todavía.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="listing-pagination px-3">
            {{ $imports->links() }}
        </div>
    </div>

    @push('page-scripts')
        <script>
            (() => {
                const branchSelect = document.getElementById('branch_id');
                const warehouseSelect = document.getElementById('warehouse_id');

                if (!branchSelect || !warehouseSelect) {
                    return;
                }

                const options = Array.from(
                    warehouseSelect.querySelectorAll('option[data-branch-id]')
                );

                const filterWarehouses = () => {
                    const branchId = branchSelect.value;
                    let selectedIsVisible = false;

                    options.forEach((option) => {
                        const visible = option.dataset.branchId === branchId;
                        option.hidden = !visible;
                        option.disabled = !visible;

                        if (visible && option.selected) {
                            selectedIsVisible = true;
                        }
                    });

                    if (!selectedIsVisible) {
                        warehouseSelect.value = '';
                    }

                    warehouseSelect.disabled = branchId === '';
                };

                branchSelect.addEventListener('change', filterWarehouses);
                filterWarehouses();
            })();
        </script>
    @endpush
</x-layouts.tenant>
