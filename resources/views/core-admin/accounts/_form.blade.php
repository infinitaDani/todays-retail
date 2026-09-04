<label for="name">Nombre</label>
<input
    id="name"
    name="name"
    value="{{ old('name', $account->name ?? '') }}"
    required
>
@error('name')
    <div class="error">{{ $message }}</div>
@enderror

<label for="ruc">RUC / cédula</label>
<input
    id="ruc"
    name="ruc"
    value="{{ old('ruc', $account->ruc ?? '') }}"
    maxlength="20"
    required
>
@error('ruc')
    <div class="error">{{ $message }}</div>
@enderror

<label for="database_name">Database name</label>
<input
    id="database_name"
    name="database_name"
    value="{{ old('database_name', $account->database_name ?? '') }}"
    maxlength="150"
    required
>
@error('database_name')
    <div class="error">{{ $message }}</div>
@enderror

<label for="status">Estado</label>
<select id="status" name="status" required>
    <option
        value="active"
        @selected(old('status', $account->status ?? 'active') === 'active')
    >
        Activo
    </option>
    <option
        value="inactive"
        @selected(old('status', $account->status ?? '') === 'inactive')
    >
        Inactivo
    </option>
</select>
@error('status')
    <div class="error">{{ $message }}</div>
@enderror

<h2>Plan de integración</h2>

<label>
    <input
        type="checkbox"
        name="contifico_enabled"
        value="1"
        @checked(old('contifico_enabled', $account->contifico_enabled ?? false))
    >
    Contífico habilitado para esta cuenta
</label>

<label for="manual_bulk_syncs_per_day">
    Sincronizaciones masivas manuales por día
</label>
<input
    id="manual_bulk_syncs_per_day"
    type="number"
    name="manual_bulk_syncs_per_day"
    min="0"
    value="{{ old('manual_bulk_syncs_per_day', $account->manual_bulk_syncs_per_day ?? '') }}"
    placeholder="Sin límite"
>
@error('manual_bulk_syncs_per_day')
    <div class="error">{{ $message }}</div>
@enderror

<label for="manual_bulk_min_interval_minutes">
    Intervalo mínimo entre sincronizaciones masivas (minutos)
</label>
<input
    id="manual_bulk_min_interval_minutes"
    type="number"
    name="manual_bulk_min_interval_minutes"
    min="0"
    value="{{ old('manual_bulk_min_interval_minutes', $account->manual_bulk_min_interval_minutes ?? '') }}"
    placeholder="Sin intervalo mínimo"
>
@error('manual_bulk_min_interval_minutes')
    <div class="error">{{ $message }}</div>
@enderror

<div class="actions">
    <button type="submit">Guardar</button>
    <a
        class="button secondary"
        href="{{ isset($account)
            ? route('admin.accounts.show', $account)
            : route('admin.accounts.index') }}"
    >
        Cancelar
    </a>
</div>
