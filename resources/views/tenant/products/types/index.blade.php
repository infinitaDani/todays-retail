<x-layouts.tenant title="Tipos de producto" subtitle="Tipos extensibles del catálogo">
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-primary" href="{{ route('products.types.create') }}">Nuevo tipo</a>
    </div>

    <div class="tr-card">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td>{{ $type->name }}</td>
                            <td>{{ $type->sort_order }}</td>
                            <td>{{ $type->is_active ? 'Activo' : 'Inactivo' }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-light" href="{{ route('products.types.edit', $type) }}">Editar</a>
                                <form class="d-inline" method="POST" action="{{ route('products.types.status', $type) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-secondary">{{ $type->is_active ? 'Inactivar' : 'Activar' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="4">No hay tipos configurados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $types->links() }}
    </div>
</x-layouts.tenant>
