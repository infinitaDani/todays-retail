@php
    $editing = isset($warehouse);
@endphp

<x-layouts.tenant
    :title="$editing ? 'Editar bodega' : 'Nueva bodega'"
    subtitle="Inventario"
>
    <div class="tr-card">
        <form
            method="POST"
            action="{{ $editing
                ? route('inventory.warehouses.update', $warehouse)
                : route('inventory.warehouses.store') }}"
        >
            @csrf

            @if ($editing)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="warehouse-name">Nombre</label>
                    <input
                        class="form-control @error('name') is-invalid @enderror"
                        id="warehouse-name"
                        name="name"
                        value="{{ old('name', $warehouse->name ?? '') }}"
                        required
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="warehouse-branch">Sucursal</label>
                    <select
                        class="form-select @error('branch_id') is-invalid @enderror"
                        id="warehouse-branch"
                        name="branch_id"
                        required
                    >
                        <option value="">Selecciona una sucursal</option>

                        @foreach ($branches as $branch)
                            <option
                                value="{{ $branch->id }}"
                                @selected(old(
                                    'branch_id',
                                    $warehouse->branch_id ?? request('branch_id'),
                                ) == $branch->id)
                            >
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
				
				<div class="mb-3">
					<label class="form-label">
						Propósitos
					</label>

					@php
						$selectedPurposes = old(
							'purposes',
							$warehouse->purposes ?? []
						);
					@endphp

					<div class="d-flex flex-wrap gap-3">
						<div class="form-check">
							<input
								class="form-check-input"
								id="warehouse-purpose-purchase"
								name="purposes[]"
								type="checkbox"
								value="purchase"
								@checked(in_array('purchase', $selectedPurposes, true))
							>

							<label
								class="form-check-label"
								for="warehouse-purpose-purchase"
							>
								Compra
							</label>
						</div>

						<div class="form-check">
							<input
								class="form-check-input"
								id="warehouse-purpose-sale"
								name="purposes[]"
								type="checkbox"
								value="sale"
								@checked(in_array('sale', $selectedPurposes, true))
							>

							<label
								class="form-check-label"
								for="warehouse-purpose-sale"
							>
								Venta
							</label>
						</div>

						<div class="form-check">
							<input
								class="form-check-input"
								id="warehouse-purpose-production"
								name="purposes[]"
								type="checkbox"
								value="production"
								@checked(in_array('production', $selectedPurposes, true))
							>

							<label
								class="form-check-label"
								for="warehouse-purpose-production"
							>
								Producción
							</label>
						</div>
					</div>

					@error('purposes')
						<div class="text-danger small mt-1">
							{{ $message }}
						</div>
					@enderror

					@error('purposes.*')
						<div class="text-danger small mt-1">
							{{ $message }}
						</div>
					@enderror
				</div>
                
				
				

                <div class="col-md-6">
                    <label class="form-label" for="warehouse-contifico-code">Código Contífico</label>
                    <input
                        class="form-control @error('contifico_code') is-invalid @enderror"
                        id="warehouse-contifico-code"
                        name="contifico_code"
                        value="{{ old('contifico_code', $warehouse->contifico_code ?? '') }}"
                    >
                    <div class="form-text">
                        Identifica esta bodega en futuras consultas de stock a Contífico.
                    </div>
                    @error('contifico_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            id="warehouse-active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            @checked(old('is_active', $warehouse->is_active ?? true))
                        >
                        <label class="form-check-label" for="warehouse-active">
                            Bodega activa
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a class="btn btn-light" href="{{ route('inventory.warehouses.index') }}">
                    Cancelar
                </a>
                <button class="btn btn-primary" type="submit">
                    Guardar bodega
                </button>
            </div>
        </form>
    </div>
</x-layouts.tenant>
