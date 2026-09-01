<x-layouts.tenant
    :title="$branch->name"
    subtitle="Sucursal"
>
    <div class="d-flex justify-content-end gap-2 mb-3">
        <a
            class="btn btn-primary"
            href="{{ route('operations.branches.edit', $branch) }}"
        >
            Editar
        </a>

        <form
            method="POST"
            action="{{ route('operations.branches.status', $branch) }}"
        >
            @csrf
            @method('PATCH')

            <button
                class="btn btn-outline-{{ $branch->status === 'active'
                    ? 'warning'
                    : 'success' }}"
                type="submit"
            >
                {{ $branch->status === 'active'
                    ? 'Desactivar'
                    : 'Activar' }}
            </button>
        </form>

        @unless ($inUse)
            <form
                method="POST"
                action="{{ route('operations.branches.destroy', $branch) }}"
                onsubmit="return confirm('¿Eliminar esta sucursal?')"
            >
                @csrf
                @method('DELETE')

                <button
                    class="btn btn-outline-danger"
                    type="submit"
                >
                    Eliminar
                </button>
            </form>
        @endunless
    </div>

    <div class="tr-card">
        <dl class="row mb-0">
            <dt class="col-sm-3">
                Código
            </dt>

            <dd class="col-sm-9">
                {{ $branch->code ?: '—' }}
            </dd>

            <dt class="col-sm-3">
                Estado
            </dt>

            <dd class="col-sm-9">
                {{ $branch->status === 'active'
                    ? 'Activa'
                    : 'Inactiva' }}
            </dd>
        </dl>

        @if ($inUse)
            <div class="alert alert-info mt-3 mb-0">
                Esta sucursal tiene colaboradores, asignaciones o historial.
                Solo puede desactivarse.
            </div>
        @endif
    </div>
</x-layouts.tenant>