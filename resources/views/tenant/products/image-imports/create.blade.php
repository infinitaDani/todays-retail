<x-layouts.tenant
    title="Importar imágenes"
    subtitle="Asocia imágenes generales por Código Catálogo"
>
    @php
        $statusLabels = [
            'analyzing' => 'Analizando',
            'processing' => 'Importando',
            'previewed' => 'Pendiente de confirmación',
            'completed' => 'Completada',
            'completed_with_errors' => 'Completada con errores',
            'failed' => 'Fallida',
            'cancelled' => 'Cancelada',
            'expired' => 'Expirada',
        ];
        $statusColors = [
            'completed' => 'success',
            'previewed' => 'info',
            'processing' => 'primary',
            'completed_with_errors' => 'warning',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            'expired' => 'secondary',
        ];
    @endphp

    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-light" href="{{ route('products.index') }}">
            <i data-lucide="arrow-left"></i>
            Volver a Productos
        </a>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="tr-card">
                <h5 class="mb-2">Seleccionar archivo ZIP</h5>
                <p class="text-muted">
                    Los nombres de las imágenes se compararán únicamente con el Código Catálogo
                    de los productos existentes. Ninguna imagen se asociará por SKU.
                </p>

                <form
                    method="POST"
                    action="{{ route('products.image-imports.preview') }}"
                    enctype="multipart/form-data"
                >
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="zip">Archivo ZIP</label>
                        <input
                            class="form-control @error('zip') is-invalid @enderror"
                            id="zip"
                            type="file"
                            name="zip"
                            accept=".zip,application/zip"
                            required
                        >
                        @error('zip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Máximo {{ $maxArchiveMegabytes }} MB y 1.000 archivos por ZIP.
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        <i data-lucide="scan-search"></i>
                        Analizar ZIP
                    </button>
                </form>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="tr-card h-100">
                <h5 class="mb-3">Antes de importar</h5>
                <ul class="text-muted mb-0">
                    <li>Formatos permitidos: JPG, JPEG, PNG y WebP.</li>
                    <li>El ZIP puede contener subcarpetas.</li>
                    <li>Las coincidencias ambiguas nunca se importan automáticamente.</li>
                    <li>La vista previa no modifica productos ni imágenes existentes.</li>
                    <li>Las variantes heredan las imágenes generales del producto.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="tr-card mt-3 p-0 overflow-hidden">
        <div class="p-3 border-bottom">
            <h5 class="mb-0">Historial de importaciones de imágenes</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Archivo</th>
                        <th>Ejecutada por</th>
                        <th>Estado</th>
                        <th>Procesadas</th>
                        <th>Importadas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($imports as $import)
                        <tr>
                            <td>{{ $import->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $import->original_filename }}</td>
                            <td>{{ $userNames[$import->core_user_id] ?? 'Usuario #' . $import->core_user_id }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $statusColors[$import->status] ?? 'primary' }}">
                                    {{ $statusLabels[$import->status] ?? $import->status }}
                                </span>
                            </td>
                            <td>{{ $import->total_count }}</td>
                            <td>{{ $import->imported_count }}</td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('products.image-imports.show', $import) }}"
                                >
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="listing-empty">
                                    No hay importaciones de imágenes todavía.
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
</x-layouts.tenant>
