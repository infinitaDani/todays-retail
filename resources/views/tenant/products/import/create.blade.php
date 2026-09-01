<x-layouts.tenant title="Importar productos" subtitle="Revisa el archivo antes de crear productos nuevos.">
    <div class="tr-card mb-4">
        <h5>Importar fácil primero. Organizar y enriquecer después.</h5>
        <p class="text-muted mb-4">
            Sube tu archivo de productos para revisar los datos antes de importarlos.
        </p>

        <form
            method="POST"
            action="{{ route('products.imports.preview') }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="mb-3">
                <label class="form-label" for="products-excel">Archivo Excel</label>
                <input
                    id="products-excel"
                    class="form-control @error('excel') is-invalid @enderror"
                    type="file"
                    name="excel"
                    accept=".xlsx,.xls"
                    required
                >
                <div class="form-text">
                    Formatos permitidos: .xlsx y .xls. El archivo no se importará todavía.
                </div>

                @error('excel')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <section class="mb-4">
                <h5 class="mb-1">Stock del archivo</h5>

                @if (! $inventorySettings->manages_inventory)
                    <input name="stock_import_mode" type="hidden" value="none">

                    <p class="text-muted mb-0">
                        La gestión de inventario está desactivada. El stock del archivo no será importado.
                    </p>
                @elseif (! $inventorySettings->inventory_by_branch)
                    <input name="stock_import_mode" type="hidden" value="none">

                    <p class="text-muted mb-0">
                        Para importar existencias, habilita Inventario por sucursal en Configuración.
                    </p>
                @else
                    <div class="form-check mb-2">
                        <input
                            class="form-check-input"
                            id="stock-import-none"
                            name="stock_import_mode"
                            type="radio"
                            value="none"
                            @checked(old('stock_import_mode', 'none') === 'none')
                        >
                        <label class="form-check-label" for="stock-import-none">
                            No importar stock
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            id="stock-import-warehouse"
                            name="stock_import_mode"
                            type="radio"
                            value="warehouse"
                            @checked(old('stock_import_mode') === 'warehouse')
                        >
                        <label class="form-check-label" for="stock-import-warehouse">
                            Importar stock en una bodega
                        </label>
                    </div>

                    <div data-warehouse-selection hidden>
                        <label class="form-label" for="import-warehouse">Bodega destino</label>
                        <select
                            id="import-warehouse"
                            class="form-select @error('warehouse_id') is-invalid @enderror"
                            name="warehouse_id"
                        >
                            <option value="">Selecciona una bodega</option>

                            @foreach ($warehouses as $warehouse)
                                <option
                                    value="{{ $warehouse->id }}"
                                    @selected(old('warehouse_id') == $warehouse->id)
                                >
                                    {{ $warehouse->branch->name }} — {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('warehouse_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-text">
                        Si no importas stock, la columna Stock será ignorada por completo.
                    </div>
                @endif

                @error('stock_import_mode')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </section>

            <button class="btn btn-primary" type="submit">
                <i data-lucide="scan-search"></i>
                Previsualizar importación
            </button>
        </form>
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <div class="p-3 border-bottom">
            <h5 class="mb-0">Historial de mis importaciones</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Importados</th>
                        <th>Existentes</th>
                        <th>Errores</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($imports as $import)
                        <tr>
                            <td>
                                {{ $import->original_filename ?: basename($import->excel_path) }}
                            </td>
                            <td>{{ $import->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ ucfirst($import->status) }}</td>
                            <td>{{ $import->created_count }}</td>
                            <td>{{ $import->existing_count }}</td>
                            <td>{{ $import->error_count }}</td>
                            <td>
                                <a class="btn btn-sm btn-light" href="{{ route('products.imports.show', $import) }}">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="listing-empty">Todavía no has realizado importaciones.</div>
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

    @if ($inventorySettings->manages_inventory && $inventorySettings->inventory_by_branch)
        @push('page-scripts')
            <script>
                const stockImportInputs = document.querySelectorAll(
                    'input[name="stock_import_mode"]',
                );
                const warehouseSelection = document.querySelector('[data-warehouse-selection]');
                const warehouseInput = document.getElementById('import-warehouse');

                const refreshWarehouseSelection = () => {
                    const importInWarehouse = document.getElementById(
                        'stock-import-warehouse',
                    ).checked;

                    warehouseSelection.hidden = !importInWarehouse;
                    warehouseInput.required = importInWarehouse;

                    if (! importInWarehouse) {
                        warehouseInput.value = '';
                    }
                };

                stockImportInputs.forEach((input) => {
                    input.addEventListener('change', refreshWarehouseSelection);
                });

                refreshWarehouseSelection();
            </script>
        @endpush
    @endif
</x-layouts.tenant>
