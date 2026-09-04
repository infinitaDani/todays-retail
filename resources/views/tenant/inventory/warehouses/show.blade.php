<x-layouts.tenant
    :title="$warehouse->name"
    subtitle="Detalle de bodega"
>
    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="d-flex justify-content-end gap-2 mb-3">
        @if ($canManage)
            <a class="btn btn-primary" href="{{ route('inventory.warehouses.edit', $warehouse) }}">
                Editar
            </a>

            <form method="POST" action="{{ route('inventory.warehouses.status', $warehouse) }}">
                @csrf
                @method('PATCH')

                <button class="btn btn-outline-warning" type="submit">
                    {{ $warehouse->is_active ? 'Desactivar' : 'Activar' }}
                </button>
            </form>

            @if ($canDelete)
                <form
                    method="POST"
                    action="{{ route('inventory.warehouses.destroy', $warehouse) }}"
                    onsubmit="return confirm('¿Eliminar esta bodega?')"
                >
                    @csrf
                    @method('DELETE')

                    <button class="btn btn-outline-danger" type="submit">
                        Eliminar
                    </button>
                </form>
            @endif
        @endif
    </div>

    <div class="tr-card">
        <dl class="row mb-0">
            <dt class="col-sm-4">Sucursal</dt>
            <dd class="col-sm-8">{{ $warehouse->branch?->name ?? '—' }}</dd>

            <dt class="col-sm-4">Propósito</dt>
            <dd class="col-sm-8">
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
			</dd>

            <dt class="col-sm-4">Código Contífico</dt>
            <dd class="col-sm-8">{{ $warehouse->contifico_code ?: '—' }}</dd>

            <dt class="col-sm-4">Estado</dt>
            <dd class="col-sm-8">{{ $warehouse->is_active ? 'Activa' : 'Inactiva' }}</dd>

            <dt class="col-sm-4">SKU con stock registrado</dt>
            <dd class="col-sm-8">{{ $warehouse->stocks_count }}</dd>

            <dt class="col-sm-4">Existencias totales</dt>
            <dd class="col-sm-8">{{ number_format((float) $totalQuantity, 3) }}</dd>
        </dl>

        @if (! $canDelete)
            <div class="alert alert-info mt-3 mb-0">
                Esta bodega tiene inventario o historial asociado y no puede eliminarse.
                Puedes desactivarla.
            </div>
        @endif
    </div>
</x-layouts.tenant>
