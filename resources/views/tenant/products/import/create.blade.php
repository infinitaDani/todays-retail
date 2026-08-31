<x-layouts.tenant title="Importar productos" subtitle="Revisa el archivo antes de crear productos nuevos.">
    <div class="tr-card mb-4">
        <h5>Importar fácil primero. Organizar y enriquecer después.</h5>
        <p class="text-muted mb-4">
            Sube tu archivo de productos para revisar los datos antes de importarlos.
        </p>

        <form method="POST" action="{{ route('products.imports.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="products-excel">Archivo Excel</label>
                <input id="products-excel" class="form-control @error('excel') is-invalid @enderror" type="file" name="excel" accept=".xlsx,.xls" required>
                <div class="form-text">Formatos permitidos: .xlsx y .xls. El archivo no se importará todavía.</div>
                @error('excel')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
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
                            <td>{{ $import->original_filename ?: basename($import->excel_path) }}</td>
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
</x-layouts.tenant>
