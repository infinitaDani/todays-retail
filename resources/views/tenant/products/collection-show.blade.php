<x-layouts.tenant
    title="{{ $collection->name }}"
    subtitle="{{ $collection->reference }}"
>
    @if ($canManage)
        <div class="d-flex justify-content-end mb-3">
            <a
                href="{{ route('products.collections.edit', $collection) }}"
                class="btn btn-primary"
            >
                Editar colección
            </a>
        </div>
    @endif

    <div class="tr-card">
        <h5>Líneas de colección</h5>

        <table class="table">
            <tbody>
                @forelse($collection->lines as $line)
                    <tr>
                        <td>
                            {{ $line->name }}
                        </td>

                        <td>
                            {{ $line->is_active ? 'Activa' : 'Inactiva' }}
                        </td>

                        <td>
                            @if ($canManage)
                                <form
                                    method="POST"
                                    action="{{ route('products.collections.lines.update', [$collection, $line]) }}"
                                >
                                    @csrf
                                    @method('PUT')

                                    <input
                                        type="hidden"
                                        name="name"
                                        value="{{ $line->name }}"
                                    >

                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="{{ $line->is_active }}"
                                    >

                                    <button
                                        class="btn btn-sm btn-light"
                                        type="submit"
                                    >
                                        Guardar
                                    </button>
                                </form>
                            @else
                                <span class="text-muted">Solo lectura</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td>
                            No hay líneas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($canManage)
            <form
                method="POST"
                action="{{ route('products.collections.lines.store', $collection) }}"
                class="row g-2"
            >
                @csrf

                <div class="col-md-4">
                    <input
                        class="form-control"
                        name="name"
                        placeholder="Nueva línea"
                        required
                    >
                </div>

                <div class="col-md-2">
                    <button
                        class="btn btn-outline-primary"
                        type="submit"
                    >
                        Agregar línea
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.tenant>
