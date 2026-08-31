<x-layouts.tenant title="{{ $product->name }}" subtitle="{{ $product->catalog_code ?: 'Sin código catálogo' }}">
    <div class="d-flex justify-content-end gap-2 mb-3">
        <a class="btn btn-primary" href="{{ route('products.edit', $product) }}">Editar</a>
        <form method="POST" action="{{ route('products.status', $product) }}">
            @csrf
            @method('PATCH')
            <button class="btn btn-outline-warning">{{ $product->is_active ? 'Desactivar' : 'Activar' }}</button>
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
