<x-layouts.tenant title="Previsualización de importación" subtitle="Ningún producto ha sido creado todavía.">
    <div class="row g-3 mb-4">
        @foreach ([
            ['Total', $summary['total'], 'primary'],
            ['Listos', $summary['ready'], 'success'],
            ['Ya existen', $summary['existing'], 'secondary'],
            ['Advertencias', $summary['warning'], 'warning'],
            ['Errores', $summary['error'], 'danger'],
        ] as [$label, $value, $color])
            <div class="col-6 col-lg">
                <div class="summary-card">
                    <div class="summary-value text-{{ $color }}">{{ $value }}</div>
                    <div class="summary-label">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="tr-card mb-3 d-flex flex-wrap gap-2 align-items-center">
        <div>
            <strong>{{ $import->original_filename }}</strong>
            <div class="text-muted small">Solo se crearán filas nuevas y válidas.</div>
        </div>
        <div class="ms-md-auto d-flex gap-2">
            <a class="btn btn-light" href="{{ route('products.imports.create') }}">Cancelar</a>
            <form method="POST" action="{{ route('products.imports.store', $import) }}">
                @csrf
                <button class="btn btn-primary" type="submit" @disabled($summary['ready'] + $summary['warning'] === 0)>
                    <i data-lucide="upload"></i>
                    Importar productos válidos
                </button>
            </form>
        </div>
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fila</th>
                        <th>Código</th>
                        <th>Código catálogo</th>
                        <th>Categoría</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Colección</th>
                        <th>PVP1</th>
                        <th>Stock</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_slice($rows, 0, 200) as $row)
                        <tr>
                            <td>{{ $row['row_number'] }}</td>
                            <td>{{ $row['sku'] ?: '—' }}</td>
                            <td>{{ $row['catalog_code'] ?: '—' }}</td>
                            <td>{{ $row['category'] ?: '—' }}</td>
                            <td>{{ $row['name'] ?: '—' }}</td>
                            <td>{{ $row['type'] ?: '—' }}</td>
                            <td>{{ $row['collection'] ?: '—' }}</td>
                            <td>{{ $row['pvp1'] ?? '—' }}</td>
                            <td>{{ $row['stock'] ?? '—' }}</td>
                            <td>
                                @php
                                    $labels = [
                                        'ready' => ['success', 'Listo para importar'],
                                        'warning' => ['warning', 'Advertencia'],
                                        'existing' => ['secondary', 'Ya existe'],
                                        'error' => ['danger', 'Error'],
                                    ];
                                    [$color, $label] = $labels[$row['status']];
                                @endphp
                                <span class="badge badge-soft-{{ $color }}">{{ $label }}</span>
                                @if ($row['messages'] !== [])
                                    <div class="small text-muted mt-1">{{ implode(' ', $row['messages']) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($rows) > 200)
            <div class="p-3 text-muted small">Se muestran las primeras 200 filas de {{ count($rows) }}.</div>
        @endif
    </div>
</x-layouts.tenant>
