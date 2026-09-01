<x-layouts.tenant title="Resultado de importación" subtitle="Resumen del archivo procesado.">
    <div class="row g-3 mb-4">
        @foreach ([
            ['Procesadas', $import->processed_count, 'primary'],
            ['Nuevas importadas', $import->created_count, 'success'],
            ['Ya existentes / omitidas', $import->existing_count, 'secondary'],
            ['Advertencias', $import->warning_count, 'warning'],
            ['Errores', $import->error_count, 'danger'],
        ] as [$label, $value, $color])
            <div class="col-6 col-lg">
                <div class="summary-card">
                    <div class="summary-value text-{{ $color }}">{{ $value }}</div>
                    <div class="summary-label">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="tr-card mb-3">
        <strong>{{ $import->original_filename ?: basename($import->excel_path) }}</strong>
        <span class="text-muted ms-2">{{ ucfirst($import->status) }}</span>
    </div>

    @if (! empty($import->errors))
        <div class="tr-card mb-3">
            <h5>Filas con errores</h5>
            <ul class="mb-0">
                @foreach ($import->errors as $error)
                    <li>
                        @if (! empty($error['row']))
                            Fila {{ $error['row'] }}
                            @if (! empty($error['sku']))
                                ({{ $error['sku'] }})
                            @endif
                            :
                        @endif
                        {{ $error['message'] ?? 'Error no especificado.' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('products.index') }}">Volver al catálogo</a>
        <a class="btn btn-light" href="{{ route('products.imports.create') }}">Ver historial</a>
    </div>
</x-layouts.tenant>
