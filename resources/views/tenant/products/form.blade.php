@php($editing = isset($product))

<x-layouts.tenant
    title="{{ $editing ? 'Editar producto' : 'Nuevo producto' }}"
    subtitle="Catálogo"
>
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
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
            let n = document.querySelectorAll('#variants .row').length;

            document.getElementById('add-variant').onclick = () => {
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
                                name="variants[${n}][sale_price]"
                                placeholder="Precio"
                            >
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
                    </div>`
                );

                n++;
            };
        </script>
    @endpush
</x-layouts.tenant>