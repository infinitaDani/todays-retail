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
                    @if ($canManage)
                        <td>
                            <input
                                class="form-control"
                                type="text"
                                name="name"
                                value="{{ $line->name }}"
                                required
                                form="line-update-{{ $line->id }}"
                            >
                        </td>

                        <td>
                            <select
                                class="form-select"
                                name="is_active"
                                form="line-update-{{ $line->id }}"
                            >
                                <option value="1" @selected($line->is_active)>
                                    Activa
                                </option>
                                <option value="0" @selected(! $line->is_active)>
                                    Inactiva
                                </option>
                            </select>
                        </td>

                        <td>
                            <div class="d-flex gap-2">
                                <form
                                    id="line-update-{{ $line->id }}"
                                    method="POST"
                                    action="{{ route('products.collections.lines.update', [$collection, $line]) }}"
                                >
                                    @csrf
                                    @method('PUT')

                                    <button
                                        class="btn btn-sm btn-primary"
                                        type="submit"
                                    >
                                        Guardar
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('products.collections.lines.destroy', [$collection, $line]) }}"
                                    onsubmit="return confirm('¿Eliminar esta línea?');"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                        type="submit"
                                    >
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    @else
                        <td>
                            {{ $line->name }}
                        </td>

                        <td>
                            {{ $line->is_active ? 'Activa' : 'Inactiva' }}
                        </td>

                        <td>
                            <span class="text-muted">Solo lectura</span>
                        </td>
                    @endif
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
