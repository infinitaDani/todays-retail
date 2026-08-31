@php($editing = isset($collection))

<x-layouts.tenant title="{{ $editing ? 'Editar colección' : 'Nueva colección' }}">
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing
                ? route('products.collections.update', $collection)
                : route('products.collections.store') }}"
        >
            @csrf

            @if($editing)
                @method('PUT')
            @endif

            <label class="form-label">
                Nombre
            </label>

            <input
                class="form-control mb-3"
                name="name"
                value="{{ old('name', $collection->name ?? '') }}"
                required
            >

            <label class="form-label">
                Referencia
            </label>

            <input
                class="form-control mb-3"
                name="reference"
                value="{{ old('reference', $collection->reference ?? '') }}"
            >

            <label class="form-label">
                Descripción
            </label>

            <textarea
                class="form-control mb-3"
                name="description"
            >{{ old('description', $collection->description ?? '') }}</textarea>

            <div class="form-check form-switch mb-3">
                <input
                    class="form-check-input"
                    name="is_active"
                    value="1"
                    type="checkbox"
                    @checked(old('is_active', $collection->is_active ?? true))
                >
                Activa
            </div>

            <button
                class="btn btn-primary"
                type="submit"
            >
                Guardar
            </button>
        </form>
    </div>
</x-layouts.tenant>