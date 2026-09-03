@php
    $editing = isset($fixtureType);
@endphp

<x-layouts.tenant
    title="{{ $editing ? 'Editar elemento' : 'Nuevo elemento' }}"
    subtitle="Tipos reutilizables dentro del Floor Plan"
>
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing ? route('merchandising.fixture-types.update', $fixtureType) : route('merchandising.fixture-types.store') }}"
        >
            @csrf

            @if ($editing)
                @method('PUT')
            @endif

            <div class="row g-3">
                @if ($editing)
                    <div class="col-12">
                        <label class="form-label">Icono actual</label>
                        <div>
                            <x-merchandising.fixture-icon
                                :fixture-type="$fixtureType"
                                :size="80"
                            />
                        </div>
                        @if (! $fixtureType->icon_path)
                            <div class="form-text">
                                Este elemento personalizado no tiene icono. Seguirá funcionando con el placeholder.
                            </div>
                        @endif
                    </div>
                @endif

                <div class="col-md-6">
                    <label class="form-label" for="fixture-name">Nombre</label>
                    <input
                        class="form-control @error('name') is-invalid @enderror"
                        id="fixture-name"
                        name="name"
                        value="{{ old('name', $fixtureType->name ?? '') }}"
                        required
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="fixture-category">Categoría</label>
                    <select
                        class="form-select @error('category') is-invalid @enderror"
                        id="fixture-category"
                        name="category"
                        required
                        @disabled($editing && $fixtureType->is_default)
                    >
                        <option
                            value="structure"
                            @selected(old('category', $fixtureType->category ?? '') === 'structure')
                        >
                            Estructura
                        </option>
                        <option
                            value="accessory"
                            @selected(old('category', $fixtureType->category ?? '') === 'accessory')
                        >
                            Accesorio
                        </option>
                    </select>
                    @error('category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if ($editing && $fixtureType->is_default)
                        <input
                            type="hidden"
                            name="category"
                            value="{{ $fixtureType->category }}"
                        >
                        <div class="form-text">
                            La categoría de un elemento default se mantiene fija.
                        </div>
                    @endif
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="fixture-order">Orden</label>
                    <input
                        class="form-control @error('sort_order') is-invalid @enderror"
                        id="fixture-order"
                        type="number"
                        min="0"
                        name="sort_order"
                        value="{{ old('sort_order', $fixtureType->sort_order ?? 0) }}"
                    >
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            id="fixture-active"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            @checked(old('is_active', $fixtureType->is_active ?? true))
                        >
                        <label class="form-check-label" for="fixture-active">Activo</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Guardar</button>
                <a class="btn btn-light" href="{{ route('merchandising.fixture-types.index') }}">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.tenant>
