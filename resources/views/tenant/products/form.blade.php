@php($editing = isset($product))

<x-layouts.tenant
    title="{{ $editing ? 'Editar producto' : 'Nuevo producto' }}"
    subtitle="Catálogo"
>
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
            enctype="multipart/form-data"
        >
            @csrf

            @if ($editing)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Código catálogo</label>
                    <input
                        class="form-control"
                        name="catalog_code"
                        value="{{ old('catalog_code', $product->catalog_code ?? '') }}"
                    >
                </div>

                <div class="col-md-8">
                    <label class="form-label">Nombre</label>
                    <input
                        class="form-control"
                        name="name"
                        value="{{ old('name', $product->name ?? '') }}"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="product-type">Tipo de producto</label>
                    <select class="form-select" id="product-type" name="product_type_id">
                        <option value="">Sin tipo</option>

                        @foreach ($productTypes as $type)
                            <option value="{{ $type->id }}" data-supply="{{ $type->normalized_name === 'suministro' ? '1' : '0' }}" @selected(old('product_type_id', $product->product_type_id ?? null) == $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4" data-supply-period hidden>
                    <label class="form-label" for="usage-period">Periodo de uso</label>
                    <input class="form-control" id="usage-period" min="1" name="usage_period" type="number" value="{{ old('usage_period', $product->usage_period ?? '') }}">
                </div>

                <div class="col-md-4" data-supply-period hidden>
                    <label class="form-label" for="usage-period-unit">Unidad</label>
                    <select class="form-select" id="usage-period-unit" name="usage_period_unit">
                        <option value="days">Días</option>
                        <option value="weeks">Semanas</option>
                        <option value="months">Meses</option>
                        <option value="years">Años</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="category_id">
                        <option value="">Sin categoría</option>

                        @foreach ($categories as $category)
                            <option
                                value="{{ $category->id }}"
                                @selected(old('category_id', $product->category_id ?? null) == $category->id)
                            >
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($settings->manages_collections)
                    <div class="col-md-4">
                        <label class="form-label">Colección</label>

                        <select class="form-select" name="product_collection_id">
                            <option value="">Sin colección</option>

                            @foreach ($collections as $collection)
                                <option
                                    value="{{ $collection->id }}"
                                    @selected(
                                        old(
                                            'product_collection_id',
                                            $product->product_collection_id ?? null
                                        ) == $collection->id
                                    )
                                >
                                    {{ $collection->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($settings->manages_collection_lines)
                    <div class="col-md-4">
                        <label class="form-label">Línea</label>

                        <select class="form-select" name="product_collection_line_id">
                            <option value="">Sin línea</option>

                            @foreach ($collections as $collection)
                                @foreach ($collection->lines as $line)
                                    <option
                                        value="{{ $line->id }}"
                                        @selected(
                                            old(
                                                'product_collection_line_id',
                                                $product->product_collection_line_id ?? null
                                            ) == $line->id
                                        )
                                    >
                                        {{ $collection->name }} · {{ $line->name }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea
                        class="form-control"
                        name="description"
                    >{{ old('description', $product->description ?? '') }}</textarea>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            value="1"
                            name="is_active"
                            @checked(old('is_active', $product->is_active ?? true))
                        >
                        Producto activo
                    </div>
                </div>
            </div>

            <hr>

            <section>
                <h5>Imágenes del producto</h5>
                <p class="text-muted">Puedes agregar varias imágenes generales. La primera será principal.</p>
                <input class="form-control" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                @if ($editing && $product->generalImages->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @foreach ($product->generalImages as $image)
                            <div>
                                <img class="rounded border" style="width:80px;height:80px;object-fit:cover" src="{{ route('products.images.show', [$product, $image]) }}" alt="{{ $product->name }}">
                                @if ($image->is_primary)
                                    <small class="d-block text-success">Principal</small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($attributes->isNotEmpty())
                <hr>

                <h5>Variantes</h5>

                <p class="text-muted">
                Agrega las variantes/SKU desde esta tabla.
                Los atributos disponibles dependen de Configuración.
                </p>

                <div id="variants">
                @foreach (old('variants', $product->variants ?? []) as $i => $variant)
                    <div class="row g-2 mb-2 border-bottom pb-2">
                        <div class="col-md-3">
                            <input
                                class="form-control"
                                name="variants[{{ $i }}][sku]"
                                value="{{ is_array($variant) ? $variant['sku'] : $variant->sku }}"
                                placeholder="SKU"
                                required
                            >
                        </div>

                        <div class="col-md-2">
                            <input
                                class="form-control"
                                name="variants[{{ $i }}][stock]"
                                value="{{ is_array($variant) ? $variant['stock'] : $variant->stock }}"
                                placeholder="Stock"
                            >
                        </div>

                        <div class="col-md-2">
                            <input
                                class="form-control"
                                name="variants[{{ $i }}][sale_price]"
                                value="{{ is_array($variant) ? $variant['sale_price'] : $variant->sale_price }}"
                                placeholder="Precio"
                            >
                        </div>

                        <div class="col-md-2">
                            <input class="form-control" name="variants[{{ $i }}][minimum_stock]" value="{{ is_array($variant) ? ($variant['minimum_stock'] ?? '') : $variant->minimum_stock }}" placeholder="Mínimo inventario">
                        </div>

                        <div class="col-md-2">
                            <input class="form-control" name="variants[{{ $i }}][pvp1]" value="{{ is_array($variant) ? ($variant['pvp1'] ?? '') : $variant->pvp1 }}" placeholder="PVP1">
                        </div>

                        @if ($settings->manages_multiple_prices)
                            <div class="col-md-2"><input class="form-control" name="variants[{{ $i }}][pvp2]" value="{{ is_array($variant) ? ($variant['pvp2'] ?? '') : $variant->pvp2 }}" placeholder="PVP2"></div>
                            <div class="col-md-2"><input class="form-control" name="variants[{{ $i }}][pvp3]" value="{{ is_array($variant) ? ($variant['pvp3'] ?? '') : $variant->pvp3 }}" placeholder="PVP3"></div>
                        @endif

                        @if ($settings->manages_distribution_price)
                            <div class="col-md-2"><input class="form-control" name="variants[{{ $i }}][distribution_price]" value="{{ is_array($variant) ? ($variant['distribution_price'] ?? '') : $variant->distribution_price }}" placeholder="PVP distribución"></div>
                        @endif

                        @foreach ($attributes as $attribute)
                            <div class="col-md-2">
                                <select
                                    class="form-select"
                                    name="variants[{{ $i }}][attribute_value_ids][]"
                                >
                                    <option value="">{{ $attribute->name }}</option>

                                    @foreach ($attribute->values as $value)
                                        <option
                                            value="{{ $value->id }}"
                                            @selected(
                                                ! is_array($variant)
                                                && $variant->attributeValues->contains('id', $value->id)
                                            )
                                        >
                                            {{ $value->value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach

                        <div class="col-12">
                            <label class="form-label">Imágenes propias de esta variante <span class="text-muted">(Opcional)</span></label>
                            <input class="form-control" type="file" name="variant_images[{{ is_array($variant) ? $i : $variant->id }}][]" accept=".jpg,.jpeg,.png,.webp" multiple>
                            <div class="form-text">Si no agregas imágenes aquí, esta variante utilizará automáticamente las imágenes generales del producto.</div>
                            @if (! is_array($variant) && $variant->images->isNotEmpty())
                                <div class="text-success small mt-1">Esta variante utiliza imágenes propias</div>
                            @else
                                <div class="text-muted small mt-1">Usando imágenes generales del producto</div>
                            @endif
                        </div>
                    </div>
                @endforeach
                </div>

                <button
                type="button"
                class="btn btn-outline-secondary"
                id="add-variant"
            >
                Agregar variante
                </button>
            @else
                <p class="text-muted mt-3 mb-0">No hay atributos habilitados. Este producto se guardará sin variantes configurables.</p>
            @endif

            <div class="mt-4">
                <button class="btn btn-primary">
                    Guardar producto
                </button>

                <a
                    class="btn btn-light"
                    href="{{ route('products.index') }}"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>

    @push('page-scripts')
        <script>
            const productType = document.querySelector('#product-type');
            const supplyPeriods = document.querySelectorAll('[data-supply-period]');

            const refreshSupplyFields = () => {
                const isSupply = productType.options[productType.selectedIndex]?.dataset.supply === '1';

                supplyPeriods.forEach((field) => {
                    field.hidden = !isSupply;
                });
            };

            productType.addEventListener('change', refreshSupplyFields);
            refreshSupplyFields();

            let n = document.querySelectorAll('#variants .row').length;
            const addVariant = document.getElementById('add-variant');

            if (addVariant) {
                addVariant.onclick = () => {
                    document.getElementById('variants').insertAdjacentHTML(
                        'beforeend',
                        `<div class="row g-2 mb-2 border-bottom pb-2">
                        <div class="col-md-3">
                            <input
                                class="form-control"
                                name="variants[${n}][sku]"
                                placeholder="SKU"
                                required
                            >
                        </div>

                        <div class="col-md-2">
                            <input
                                class="form-control"
                                name="variants[${n}][stock]"
                                placeholder="Stock"
                            >
                        </div>

                        <div class="col-md-2">
                            <input
                                class="form-control"
                                name="variants[${n}][minimum_stock]"
                                placeholder="Mínimo inventario"
                            >
                        </div>

                        <div class="col-md-2">
                            <input
                                class="form-control"
                                name="variants[${n}][pvp1]"
                                placeholder="PVP1"
                            >
                        </div>

                        <div class="col-md-2">
                            <input
                                class="form-control"
                                name="variants[${n}][pvp1_with_tax]"
                                placeholder="PVP1 + IVA"
                            >
                        </div>

                        @if ($settings->manages_multiple_prices)
                            <div class="col-md-2">
                                <input class="form-control" name="variants[${n}][pvp2]" placeholder="PVP2">
                            </div>
                            <div class="col-md-2">
                                <input class="form-control" name="variants[${n}][pvp2_with_tax]" placeholder="PVP2 + IVA">
                            </div>
                            <div class="col-md-2">
                                <input class="form-control" name="variants[${n}][pvp3]" placeholder="PVP3">
                            </div>
                            <div class="col-md-2">
                                <input class="form-control" name="variants[${n}][pvp3_with_tax]" placeholder="PVP3 + IVA">
                            </div>
                        @endif

                        @if ($settings->manages_distribution_price)
                            <div class="col-md-2">
                                <input class="form-control" name="variants[${n}][distribution_price]" placeholder="PVP distribución">
                            </div>
                            <div class="col-md-2">
                                <input class="form-control" name="variants[${n}][distribution_price_with_tax]" placeholder="PVP distribución + IVA">
                            </div>
                        @endif

                        <div class="col-md-2">
                            <input class="form-control" name="variants[${n}][tax_rate]" placeholder="% IVA">
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" id="sale-${n}" name="variants[${n}][is_for_sale]" type="checkbox" value="1" checked>
                                <label class="form-check-label" for="sale-${n}">Para la venta</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" id="purchase-${n}" name="variants[${n}][is_for_purchase]" type="checkbox" value="1">
                                <label class="form-check-label" for="purchase-${n}">Para la compra</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" id="inventory-${n}" name="variants[${n}][is_inventory_item]" type="checkbox" value="1" checked>
                                <label class="form-check-label" for="inventory-${n}">Inventariable</label>
                            </div>
                        </div>

                        @foreach ($attributes as $attribute)
                            <div class="col-md-2">
                                <select
                                    class="form-select"
                                    name="variants[${n}][attribute_value_ids][]"
                                >
                                    <option value="">{{ $attribute->name }}</option>

                                    @foreach ($attribute->values as $value)
                                        <option value="{{ $value->id }}">
                                            {{ $value->value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach

                        <div class="col-12">
                            <label class="form-label">Imágenes propias de esta variante <span class="text-muted">(Opcional)</span></label>
                            <input class="form-control" type="file" name="variant_images[${n}][]" accept=".jpg,.jpeg,.png,.webp" multiple>
                            <div class="form-text">Si no agregas imágenes aquí, esta variante utilizará automáticamente las imágenes generales del producto.</div>
                            <div class="text-muted small mt-1">Usando imágenes generales del producto</div>
                        </div>
                    </div>`
                    );

                    n++;
                };
            }
        </script>
    @endpush
</x-layouts.tenant>
