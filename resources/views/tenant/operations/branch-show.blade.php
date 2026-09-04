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

    <div class="tr-card mt-3">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h5 class="mb-1">Bodegas</h5>
                <p class="text-muted mb-0">
                    Ubicaciones de inventario vinculadas a esta sucursal.
                </p>
            </div>

            <div class="d-flex gap-2">
                <a
                    class="btn btn-outline-primary"
                    href="{{ route('inventory.warehouses.index', ['branch_id' => $branch->id]) }}"
                >
                    Ver todas
                </a>

                @if ($canManageWarehouses)
                    <a
                        class="btn btn-primary"
                        href="{{ route('inventory.warehouses.create', ['branch_id' => $branch->id]) }}"
                    >
                        <i data-lucide="plus" class="me-1"></i>
                        Nueva bodega
                    </a>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Bodega</th>
                        <th>Propósito</th>
                        <th>Código Contífico</th>
                        <th>Estado</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($branch->warehouses as $warehouse)
                        <tr>
                            <td class="fw-semibold">{{ $warehouse->name }}</td>
                            <td>
								@php
									$purposeLabels = [
										'purchase' => 'Compra',
										'sale' => 'Venta',
										'production' => 'Producción',
									];

									$warehousePurposes = collect($warehouse->purposes ?? [])
										->map(
											fn ($purpose) => $purposeLabels[$purpose] ?? $purpose
										);
								@endphp

								{{ $warehousePurposes->isNotEmpty()
									? $warehousePurposes->join(' · ')
									: '—'
								}}
							</td>
                            <td>{{ $warehouse->contifico_code ?: '—' }}</td>
                            <td>{{ $warehouse->is_active ? 'Activa' : 'Inactiva' }}</td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('inventory.warehouses.show', $warehouse) }}"
                                >
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">
                                Esta sucursal todavía no tiene bodegas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
