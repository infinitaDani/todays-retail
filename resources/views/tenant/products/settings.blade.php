<x-layouts.tenant title="Configuración de Productos" subtitle="Precios, impuestos y variantes">
    <div class="tr-card">
        <form method="POST" action="{{ route('products.settings.update') }}">
            @csrf
            @method('PUT')

            <h5 class="mb-3">Estructura comercial</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="collections" name="manages_collections" type="checkbox" value="1" @checked($settings->manages_collections)>
                        <label class="form-check-label" for="collections">Gestiona colecciones</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="lines" name="manages_collection_lines" type="checkbox" value="1" @checked($settings->manages_collection_lines)>
                        <label class="form-check-label" for="lines">Gestiona líneas</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="multiple-prices" name="manages_multiple_prices" type="checkbox" value="1" @checked($settings->manages_multiple_prices)>
                        <label class="form-check-label" for="multiple-prices">Gestiona múltiples PVP</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="distribution" name="manages_distribution_price" type="checkbox" value="1" @checked($settings->manages_distribution_price)>
                        <label class="form-check-label" for="distribution">Gestiona PVP de distribución</label>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <h5 class="mb-3">Impuestos</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" id="taxes" name="manages_taxes" type="checkbox" value="1" @checked($settings->manages_taxes)>
                        <label class="form-check-label" for="taxes">Gestiona impuestos</label>
                    </div>
                </div>
                <div class="col-md-4"><label class="form-label">% IVA</label><input class="form-control" name="vat_percent" type="number" step="0.0001" value="{{ old('vat_percent', $settings->vat_percent) }}"></div>
                <div class="col-md-4"><label class="form-label">% ICE</label><input class="form-control" name="ice_percent" type="number" step="0.0001" value="{{ old('ice_percent', $settings->ice_percent) }}"></div>
            </div>

            <hr class="my-4">
            <h5 class="mb-3">Atributos para variantes</h5>
            @foreach ($attributes as $attribute)
                <div class="row align-items-center border-bottom py-3">
                    <div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" name="attributes[{{ $attribute->id }}][name]" value="{{ $attribute->name }}"></div>
                    <div class="col-md-5"><label class="form-label">Valores separados por coma</label><input class="form-control" name="attributes[{{ $attribute->id }}][values]" value="{{ $attribute->values->pluck('value')->join(', ') }}"></div>
                    <div class="col-md-3"><div class="form-check form-switch mt-4"><input class="form-check-input" id="attribute-{{ $attribute->id }}" name="attributes[{{ $attribute->id }}][is_enabled]" type="checkbox" value="1" @checked($attribute->is_enabled)><label class="form-check-label" for="attribute-{{ $attribute->id }}">Habilitado</label></div></div>
                </div>
            @endforeach

            <div class="mt-4"><button class="btn btn-primary">Guardar configuración</button></div>
        </form>
    </div>
</x-layouts.tenant>
