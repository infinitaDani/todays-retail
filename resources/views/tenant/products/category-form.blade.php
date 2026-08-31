@php($editing = isset($category))

<x-layouts.tenant title="{{ $editing ? 'Editar categoría' : 'Nueva categoría' }}">
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing
                ? route('products.categories.update', $category)
                : route('products.categories.store') }}"
        >
            @csrf

            @if($editing)
                @method('PUT')
            @endif

            <label class="form-label">
                Categoría principal
            </label>

            <select
                class="form-select mb-3"
                name="parent_id"
            >
                <option value="">
                    Sin categoría principal
                </option>

                @foreach($parents as $parent)
                    <option
                        value="{{ $parent->id }}"
                        @selected(old('parent_id', $category->parent_id ?? null) == $parent->id)
                    >
                        {{ $parent->name }}
                    </option>
                @endforeach
            </select>

            <label class="form-label">
                Nombre
            </label>

            <input
                class="form-control mb-3"
                name="name"
                value="{{ old('name', $category->name ?? '') }}"
                required
            >

            <label class="form-label">
                Descripción
            </label>

            <textarea
                class="form-control mb-3"
                name="description"
            >{{ old('description', $category->description ?? '') }}</textarea>

            <div class="form-check form-switch mb-3">
                <input
                    class="form-check-input"
                    name="is_active"
                    value="1"
                    type="checkbox"
                    @checked(old('is_active', $category->is_active ?? true))
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