<x-layouts.tenant
    title="Elementos de merchandising"
    subtitle="Estructuras y accesorios disponibles para Floor Plans"
>
    <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
        <a class="btn btn-light" href="{{ route('merchandising.floor-plan') }}">
            <i data-lucide="layout-template"></i>
            Floor Plan
        </a>
        <a class="btn btn-primary" href="{{ route('merchandising.fixture-types.create') }}">
            <i data-lucide="plus"></i>
            Nuevo elemento
        </a>
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Elemento</th>
                        <th>Categoría</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fixtureTypes as $fixtureType)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <x-merchandising.fixture-icon
                                        :fixture-type="$fixtureType"
                                        :size="52"
                                    />
                                    <div>
                                        <div class="fw-semibold">{{ $fixtureType->name }}</div>
                                        @if ($fixtureType->is_default)
                                            <span class="badge badge-soft-primary">Default</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $fixtureType->categoryLabel() }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $fixtureType->is_active ? 'success' : 'warning' }}">
                                    {{ $fixtureType->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('merchandising.fixture-types.edit', $fixtureType) }}"
                                >
                                    Editar
                                </a>
                                <form
                                    class="d-inline"
                                    method="POST"
                                    action="{{ route('merchandising.fixture-types.toggle', $fixtureType) }}"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                        {{ $fixtureType->is_active ? 'Inactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="listing-empty">
                                    No hay elementos de merchandising configurados.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="listing-pagination px-3">
            {{ $fixtureTypes->links() }}
        </div>
    </div>
</x-layouts.tenant>
