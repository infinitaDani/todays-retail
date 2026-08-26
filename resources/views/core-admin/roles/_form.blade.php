<label for="name">Nombre</label><input id="name" name="name" value="{{ old('name', $role->name ?? '') }}" maxlength="100" required>@error('name')<div class="error">{{ $message }}</div>@enderror
<label for="code">Código</label><input id="code" name="code" value="{{ old('code', $role->code ?? '') }}" maxlength="100" required>@error('code')<div class="error">{{ $message }}</div>@enderror
<div class="actions"><button type="submit">Guardar</button><a class="button secondary" href="{{ route('admin.roles.index') }}">Cancelar</a></div>
