@php
    $editing = isset($branch);
@endphp

<x-layouts.tenant
    :title="$editing ? 'Editar sucursal' : 'Nueva sucursal'"
    subtitle="Estructura operativa"
>
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing
                ? route('operations.branches.update', $branch)
                : route('operations.branches.store') }}"
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
                        value="{{ old('name', $branch->name ?? '') }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Código
                    </label>

                    <input
                        class="form-control"
                        name="code"
                        value="{{ old('code', $branch->code ?? '') }}"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Estado
                    </label>

                    <select
                        class="form-select"
                        name="status"
                    >
                        <option
                            value="active"
                            @selected(
                                old('status', $branch->status ?? 'active') === 'active'
                            )
                        >
                            Activa
                        </option>

                        <option
                            value="inactive"
                            @selected(
                                old('status', $branch->status ?? '') === 'inactive'
                            )
                        >
                            Inactiva
                        </option>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Guardar sucursal
                </button>

                <a
                    class="btn btn-light"
                    href="{{ $editing
                        ? route('operations.branches.show', $branch)
                        : route('operations.branches') }}"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.tenant>