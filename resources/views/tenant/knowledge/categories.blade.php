<x-layouts.tenant
    title="Categorías"
    subtitle="Organiza la base de conocimientos"
>
    <div class="d-flex justify-content-end mb-3">
        <a
            class="btn btn-primary"
            href="{{ route('knowledge.categories.create') }}"
        >
            <i data-lucide="plus"></i>
            Nueva categoría
        </a>
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Artículos</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th class="text-end">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>
                                <i
                                    data-lucide="{{ $category->icon ?: 'folder' }}"
                                    class="me-2"
                                ></i>

                                {{ $category->name }}

                                <div class="small text-muted">
                                    {{ $category->description }}
                                </div>
                            </td>

                            <td>
                                {{ $category->slug }}
                            </td>

                            <td>
                                {{ $category->articles_count }}
                            </td>

                            <td>
                                {{ $category->sort_order }}
                            </td>

                            <td>
                                <span
                                    class="badge badge-soft-{{ $category->is_active
                                        ? 'success'
                                        : 'warning' }}"
                                >
                                    {{ $category->is_active
                                        ? 'Activa'
                                        : 'Inactiva' }}
                                </span>
                            </td>

                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('knowledge.categories.edit', $category) }}"
                                    title="Editar"
                                >
                                    <i data-lucide="pencil"></i>
                                </a>

                                @if (! $category->articles_count)
                                    <form
                                        class="d-inline"
                                        method="POST"
                                        action="{{ route('knowledge.categories.destroy', $category) }}"
                                        onsubmit="return confirm('¿Eliminar categoría?')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            class="btn btn-sm btn-light text-danger"
                                            type="submit"
                                            title="Eliminar"
                                        >
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="listing-empty">
                                    Aún no hay categorías.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="listing-pagination px-3">
            {{ $categories->links() }}
        </div>
    </div>
</x-layouts.tenant>