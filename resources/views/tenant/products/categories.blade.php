<x-layouts.tenant
    title="Categorías"
    subtitle="Máximo dos niveles"
>
    @if ($canManage)
        <div class="d-flex justify-content-end mb-3">
            <a
                class="btn btn-primary"
                href="{{ route('products.categories.create') }}"
            >
                Nueva categoría
            </a>
        </div>
    @endif

    <div class="tr-card">
        <table class="table table-custom">
            <thead>
                <tr>
                    <th>Categoría principal</th>
                    <th>Categoría</th>
                    <th>Productos</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($categories as $c)
                    <tr>
                        <td>
                            {{ $c->parent?->name ?: ($c->parent_id ? '—' : $c->name) }}
                        </td>

                        <td>
                            {{ $c->parent_id ? $c->name : '—' }}
                        </td>

                        <td>
                            {{ $c->products_count }}
                        </td>

                        <td>
                            {{ $c->is_active ? 'Activa' : 'Inactiva' }}
                        </td>

                        <td>
                            @if ($canManage)
                                <a
                                    href="{{ route('products.categories.edit', $c) }}"
                                    class="btn btn-sm btn-light"
                                >
                                    <i data-lucide="pencil"></i>
                                </a>

                                <form
                                    class="d-inline"
                                    method="POST"
                                    action="{{ route('products.categories.destroy', $c) }}"
                                    onsubmit="return confirm('¿Eliminar esta categoría?')"
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
                            @else
                                <span class="text-muted">Solo lectura</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            No hay categorías.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $categories->links() }}
    </div>
</x-layouts.tenant>
