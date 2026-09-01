<x-layouts.tenant
    title="Colecciones"
    subtitle="Organiza productos por colección y línea"
>
    @if ($canManage)
        <div class="d-flex justify-content-end mb-3">
            <a
                class="btn btn-primary"
                href="{{ route('products.collections.create') }}"
            >
                Nueva colección
            </a>
        </div>
    @endif

    <div class="tr-card">
        <table class="table table-custom">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Referencia</th>
                    <th>Líneas</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse($collections as $collection)
                    <tr>
                        <td>
                            {{ $collection->name }}
                        </td>

                        <td>
                            {{ $collection->reference ?: '—' }}
                        </td>

                        <td>
                            {{ $collection->lines_count ?? $collection->lines->count() }}
                        </td>

                        <td>
                            {{ $collection->is_active ? 'Activa' : 'Inactiva' }}
                        </td>

                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <a
                                    href="{{ route('products.collections.show', $collection) }}"
                                    class="btn btn-sm btn-light"
                                >
                                    <i data-lucide="eye"></i>
                                </a>
                                @if ($canManage)
                                    <form
                                        method="POST"
                                        action="{{ route('products.collections.destroy', $collection) }}"
                                        onsubmit="return confirm('¿Eliminar esta colección?')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            class="btn btn-sm btn-outline-danger"
                                            type="submit"
                                        >
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </form>

                                    <a
                                        href="{{ route('products.collections.edit', $collection) }}"
                                        class="btn btn-sm btn-light"
                                    >
                                        <i data-lucide="pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            No hay colecciones.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $collections->links() }}
    </div>
</x-layouts.tenant>
