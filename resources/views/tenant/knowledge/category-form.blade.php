@php
    $editing = isset($category);
@endphp

<x-layouts.tenant
    :title="$editing ? 'Editar categoría' : 'Nueva categoría'"
    subtitle="Knowledge Center"
>
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing
                ? route('knowledge.categories.update', $category)
                : route('knowledge.categories.store') }}"
        >
            @csrf

            @if ($editing)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        Nombre
                    </label>

                    <input
                        class="form-control"
                        name="name"
                        value="{{ old('name', $category->name ?? '') }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Slug
                        <span class="text-muted">
                            (automático si se deja vacío)
                        </span>
                    </label>

                    <input
                        class="form-control"
                        name="slug"
                        value="{{ old('slug', $category->slug ?? '') }}"
                    >
                </div>

                <div class="col-md-8">
                    <label class="form-label">
                        Descripción
                    </label>

                    <textarea
                        class="form-control"
                        name="description"
                        rows="3"
                    >{{ old('description', $category->description ?? '') }}</textarea>
                </div>

                <div class="col-md-2">
                    <label class="form-label">
                        Icono Lucide
                    </label>

                    <input
                        class="form-control"
                        name="icon"
                        value="{{ old('icon', $category->icon ?? 'folder') }}"
                    >
                </div>

                <div class="col-md-2">
                    <label class="form-label">
                        Orden
                    </label>

                    <input
                        class="form-control"
                        type="number"
                        min="0"
                        name="sort_order"
                        value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                    >
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            id="active"
                            @checked(old(
                                'is_active',
                                $category->is_active ?? true
                            ))
                        >

                        <label
                            class="form-check-label"
                            for="active"
                        >
                            Categoría activa
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Guardar categoría
                </button>

                <a
                    class="btn btn-light"
                    href="{{ route('knowledge.categories') }}"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.tenant>