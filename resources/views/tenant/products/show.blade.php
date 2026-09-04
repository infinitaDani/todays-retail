<x-layouts.tenant
    title="{{ $product->name }}"
    subtitle="{{ $product->catalog_code ?: 'Sin código catálogo' }}"
>
    <div class="d-flex justify-content-end gap-2 mb-3">
        <a class="btn btn-primary" href="{{ route('products.edit', $product) }}">
            Editar
        </a>

        <form method="POST" action="{{ route('products.status', $product) }}">
            @csrf
            @method('PATCH')

            <button class="btn btn-outline-warning">
                {{ $product->is_active ? 'Desactivar' : 'Activar' }}
            </button>
        </form>

        <form
            method="POST"
            action="{{ route('products.destroy', $product) }}"
            onsubmit="return confirm('¿Eliminar este producto y sus variantes? Esta acción no se puede deshacer.')"
        >
            @csrf
            @method('DELETE')

            <button class="btn btn-outline-danger">Eliminar</button>
        </form>
    </div>

    <div class="tr-card mb-3">
        <dl class="row mb-0">
            <dt class="col-md-3">Tipo</dt>
            <dd class="col-md-9">{{ $product->type?->name ?: '—' }}</dd>

            <dt class="col-md-3">Categoría</dt>
            <dd class="col-md-9">{{ $product->category?->name ?: '—' }}</dd>

            <dt class="col-md-3">Colección</dt>
            <dd class="col-md-9">{{ $product->collection?->name ?: '—' }}</dd>

            <dt class="col-md-3">Periodo de uso</dt>
            <dd class="col-md-9">
                {{ $product->usage_period ? $product->usage_period . ' ' . $product->usage_period_unit : '—' }}
            </dd>
        </dl>
    </div>

    @if ($canSynchronizeStock)
        <div class="tr-card mb-3">
            <h5 class="mb-1">Actualizar stock desde Contífico</h5>
            <p class="text-muted mb-3">
                Sincroniza todas las variantes de este producto usando su SKU exacto.
            </p>

            <form
                class="row g-3 align-items-end"
                method="POST"
                action="{{ route('inventory.sync.product', $product) }}"
            >
                @csrf

                <div class="col-lg-8">
                    <label class="form-label" for="product-sync-warehouse">
                        Bodega
                    </label>
                    <select
                        class="form-select"
                        id="product-sync-warehouse"
                        name="warehouse_id"
                    >
                        <option value="">Todas las bodegas autorizadas</option>
                        @foreach ($syncWarehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">
                                {{ $warehouse->branch?->name }} — {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-4">
                    <button class="btn btn-outline-primary w-100" type="submit">
                        <i data-lucide="refresh-cw" class="me-1"></i>
                        Actualizar stock
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="tr-card mb-3">
        <h5>Imágenes del producto</h5>

        <form
            class="mb-3"
            method="POST"
            action="{{ route('products.images.store', $product) }}"
            enctype="multipart/form-data"
        >
            @csrf

            <input
                class="form-control mb-2"
                type="file"
                name="images[]"
                accept=".jpg,.jpeg,.png,.webp"
                multiple
                required
            >
            <button class="btn btn-outline-primary">Agregar imágenes generales</button>
        </form>

        <div class="d-flex flex-wrap gap-3">
            @foreach ($product->generalImages as $image)
                <div>
                    <img
                        class="rounded border"
                        style="width: 110px; height: 110px; object-fit: cover"
                        src="{{ route('products.images.show', [$product, $image]) }}"
                        alt="{{ $image->alt_text ?: $product->name }}"
                    >

                    <div class="mt-1 d-flex gap-1">
                        @if (! $image->is_primary)
                            <form
                                method="POST"
                                action="{{ route('products.images.primary', [$product, $image]) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <button class="btn btn-sm btn-light">Principal</button>
                            </form>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('products.images.destroy', [$product, $image]) }}"
                            onsubmit="return confirm('¿Eliminar esta imagen?')"
                        >
                            @csrf
                            @method('DELETE')

                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="tr-card">
        <h5>Variantes y datos comerciales</h5>

        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Stock total</th>
                        <th>PVP1</th>
                        <th>Venta</th>
                        <th>Compra</th>
                        <th>Inventariable</th>
                        <th>Estado</th>
                        @if ($canSynchronizeStock)
                            <th class="text-end">Stock Contífico</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                    @forelse ($product->variants as $variant)
                        <tr>
                            <td>{{ $variant->sku }}</td>
                            <td>{{ $variant->inventoryStocks->sum('quantity') }}</td>
                            <td>{{ $variant->pvp1 ?? $variant->sale_price ?? '—' }}</td>
                            <td>{{ $variant->is_for_sale ? 'Sí' : 'No' }}</td>
                            <td>{{ $variant->is_for_purchase ? 'Sí' : 'No' }}</td>
                            <td>{{ $variant->is_inventory_item ? 'Sí' : 'No' }}</td>
                            <td>{{ $variant->is_active ? 'Activa' : 'Inactiva' }}</td>
                            @if ($canSynchronizeStock)
                                <td class="text-end">
                                    <form
                                        method="POST"
                                        action="{{ route('inventory.sync.variant', $variant) }}"
                                    >
                                        @csrf

                                        <button class="btn btn-sm btn-light" type="submit">
                                            Actualizar SKU
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>

                        @if ($variant->inventoryStocks->isNotEmpty())
                            <tr>
                                <td
                                    colspan="{{ $canSynchronizeStock ? 8 : 7 }}"
                                    class="small text-muted"
                                >
                                    <strong>Desglose:</strong>
                                    <ul class="mb-0 mt-1">
                                        @foreach ($variant->inventoryStocks as $stock)
                                            <li>
                                                {{ $stock->warehouse->branch->name }}
                                                · {{ $stock->warehouse->name }}
                                                · {{ $stock->quantity }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td
                                colspan="{{ $canSynchronizeStock ? 8 : 7 }}"
                                class="text-muted"
                            >
                                Producto sin variantes configurables.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
