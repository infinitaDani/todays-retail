<x-layouts.tenant title="{{ $product->name }}" subtitle="{{ $product->catalog_code ?: 'Sin código catálogo' }}">
    <div class="d-flex justify-content-end gap-2 mb-3">
        <a class="btn btn-primary" href="{{ route('products.edit', $product) }}">Editar</a>
        <form method="POST" action="{{ route('products.status', $product) }}">
            @csrf
            @method('PATCH')
            <button class="btn btn-outline-warning">{{ $product->is_active ? 'Desactivar' : 'Activar' }}</button>
        </form>
        <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('¿Eliminar este producto y sus variantes? Esta acción no se puede deshacer.')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger">Eliminar</button>
        </form>
    </div>

    <div class="tr-card mb-3">
        <dl class="row mb-0">
            <dt class="col-md-3">Tipo</dt><dd class="col-md-9">{{ $product->type?->name ?: '—' }}</dd>
            <dt class="col-md-3">Categoría</dt><dd class="col-md-9">{{ $product->category?->name ?: '—' }}</dd>
            <dt class="col-md-3">Colección</dt><dd class="col-md-9">{{ $product->collection?->name ?: '—' }}</dd>
            <dt class="col-md-3">Periodo de uso</dt><dd class="col-md-9">{{ $product->usage_period ? $product->usage_period.' '.$product->usage_period_unit : '—' }}</dd>
        </dl>
    </div>

    <div class="tr-card mb-3">
        <h5>Imágenes del producto</h5>
        <form class="mb-3" method="POST" action="{{ route('products.images.store', $product) }}" enctype="multipart/form-data">
            @csrf
            <input class="form-control mb-2" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple required>
            <button class="btn btn-outline-primary">Agregar imágenes generales</button>
        </form>
        <div class="d-flex flex-wrap gap-3">
            @foreach ($product->generalImages as $image)
                <div>
                    <img class="rounded border" style="width:110px;height:110px;object-fit:cover" src="{{ route('products.images.show', [$product, $image]) }}" alt="{{ $image->alt_text ?: $product->name }}">
                    <div class="mt-1 d-flex gap-1">
                        @if (! $image->is_primary)
                            <form method="POST" action="{{ route('products.images.primary', [$product, $image]) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-light">Principal</button></form>
                        @endif
                        <form method="POST" action="{{ route('products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('¿Eliminar esta imagen?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="tr-card">
        <h5>Variantes y datos comerciales</h5>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead><tr><th>SKU</th><th>Stock / mínimo</th><th>PVP1</th><th>Venta</th><th>Compra</th><th>Inventariable</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($product->variants as $variant)
                        <tr>
                            <td>{{ $variant->sku }}</td>
                            <td>{{ $variant->stock ?? '—' }} / {{ $variant->minimum_stock ?? '—' }}</td>
                            <td>{{ $variant->pvp1 ?? $variant->sale_price ?? '—' }}</td>
                            <td>{{ $variant->is_for_sale ? 'Sí' : 'No' }}</td>
                            <td>{{ $variant->is_for_purchase ? 'Sí' : 'No' }}</td>
                            <td>{{ $variant->is_inventory_item ? 'Sí' : 'No' }}</td>
                            <td>{{ $variant->is_active ? 'Activa' : 'Inactiva' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">Producto sin variantes configurables.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
