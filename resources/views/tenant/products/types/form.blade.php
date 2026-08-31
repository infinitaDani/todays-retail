@php
    $editing = isset($productType);
@endphp

<x-layouts.tenant title="{{ $editing ? 'Editar tipo de producto' : 'Nuevo tipo de producto' }}">
    <div class="tr-card">
        <form method="POST" action="{{ $editing ? route('products.types.update', $productType) : route('products.types.store') }}">
            @csrf

            @if ($editing)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="type-name">Nombre</label>
                    <input class="form-control" id="type-name" name="name" value="{{ old('name', $productType->name ?? '') }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="type-order">Orden</label>
                    <input class="form-control" id="type-order" min="0" name="sort_order" type="number" value="{{ old('sort_order', $productType->sort_order ?? 0) }}">
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="type-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $productType->is_active ?? true))>
                        <label class="form-check-label" for="type-active">Activo</label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Guardar</button>
                <a class="btn btn-light" href="{{ route('products.types.index') }}">Cancelar</a>
            </div>
        </form>
    </div>
</x-layouts.tenant>
