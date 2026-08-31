<x-layouts.tenant title="Catálogo" subtitle="Productos y variantes de la cuenta activa">
    <div class="row g-3 mb-4">
        @foreach ([
            ['Productos', $summary['total'], 'package', 'primary'],
            ['Activos', $summary['active'], 'circle-check', 'success'],
            ['Inactivos', $summary['inactive'], 'pause-circle', 'warning'],
            ['Variantes', $summary['variants'], 'layers', 'primary'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-xl-3">
                <div class="summary-card">
                    <span class="summary-icon bg-{{ $color }}-subtle text-{{ $color }}"><i data-lucide="{{ $icon }}"></i></span>
                    <div class="summary-value">{{ $value }}</div>
                    <div class="summary-label">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <form class="tr-card mb-3" method="GET">
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Nombre, código catálogo o SKU"></div>
            <div class="col-md-2">
                <select class="form-select" name="category_id"><option value="">Categoría</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((int) request('category_id') === $category->id)>{{ $category->name }}</option>@endforeach</select>
            </div>
            @if ($settings->manages_collections)
                <div class="col-md-2"><select class="form-select" name="product_collection_id"><option value="">Colección</option>@foreach ($collections as $collection)<option value="{{ $collection->id }}" @selected((int) request('product_collection_id') === $collection->id)>{{ $collection->name }}</option>@endforeach</select></div>
            @endif
            @if ($settings->manages_collection_lines)
                <div class="col-md-2"><select class="form-select" name="product_collection_line_id"><option value="">Línea</option>@foreach ($lines as $line)<option value="{{ $line->id }}" @selected((int) request('product_collection_line_id') === $line->id)>{{ $line->name }}</option>@endforeach</select></div>
            @endif
            <div class="col-md-2"><select class="form-select" name="status"><option value="">Estado</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select></div>
            @foreach ($attributes as $attribute)
                <div class="col-md-2"><select class="form-select" name="attribute_{{ $attribute->id }}"><option value="">{{ $attribute->name }}</option>@foreach ($attribute->values as $value)<option value="{{ $value->id }}" @selected((int) request('attribute_'.$attribute->id) === $value->id)>{{ $value->value }}</option>@endforeach</select></div>
            @endforeach
            <div class="col-12"><button class="btn btn-primary">Filtrar</button><a class="btn btn-light" href="{{ route('products.index') }}">Limpiar</a><a class="btn btn-primary float-end" href="{{ route('products.create') }}"><i data-lucide="plus"></i> Nuevo producto</a></div>
        </div>
    </form>

    <div class="tr-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th></th>
                    <th>Código catálogo</th>
                    <th>Producto</th>
                    <th>Categoría</th>

                    @if ($settings->manages_collections)
                        <th>Colección</th>
                    @endif

                    @if ($settings->manages_collection_lines)
                        <th>Línea</th>
                    @endif

                    <th>Variantes</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            <input
                                form="bulk-form"
                                type="checkbox"
                                name="product_ids[]"
                                value="{{ $product->id }}"
                            >
                        </td>

                        <td>{{ $product->catalog_code ?: '—' }}</td>
                        <td class="fw-semibold">{{ $product->name }}</td>
                        <td>{{ $product->category?->name ?: '—' }}</td>

                        @if ($settings->manages_collections)
                            <td>{{ $product->collection?->name ?: '—' }}</td>
                        @endif

                        @if ($settings->manages_collection_lines)
                            <td>{{ $product->line?->name ?: '—' }}</td>
                        @endif

                        <td>{{ $product->variants_count }}</td>
                        <td>{{ $product->variants_sum_stock ?? 0 }}</td>

                        <td>
                            <span class="badge badge-soft-{{ $product->is_active ? 'success' : 'warning' }}">
                                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <td>
                            <a
                                class="btn btn-sm btn-light"
                                href="{{ route('products.show', $product) }}"
                            >
                                <i data-lucide="eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="listing-empty">
                                No hay productos todavía.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="listing-pagination px-3">
        {{ $products->links() }}
    </div>
</div>

    <form id="bulk-form" class="tr-card mt-3" method="POST" action="{{ route('products.bulk') }}">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Categoría masiva</label><select class="form-select" name="category_id"><option value="">No cambiar</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            @if ($settings->manages_collections)
                <div class="col-md-3"><label class="form-label">Colección masiva</label><select class="form-select" name="product_collection_id"><option value="">No cambiar</option>@foreach ($collections as $collection)<option value="{{ $collection->id }}">{{ $collection->name }}</option>@endforeach</select></div>
            @endif
            @if ($settings->manages_collection_lines)
                <div class="col-md-3"><label class="form-label">Línea masiva</label><select class="form-select" name="product_collection_line_id"><option value="">No cambiar</option>@foreach ($lines as $line)<option value="{{ $line->id }}">{{ $line->collection->name ?? 'Colección' }} · {{ $line->name }}</option>@endforeach</select></div>
            @endif
            <div class="col-md-2"><label class="form-label">Estado</label><select class="form-select" name="is_active"><option value="">No cambiar</option><option value="1">Activar</option><option value="0">Desactivar</option></select></div>
            <div class="col-md-2"><button class="btn btn-outline-primary">Aplicar a seleccionados</button></div>
        </div>
    </form>
</x-layouts.tenant>
