<x-layouts.tenant title="Editar colaborador" subtitle="{{ $membership->user->name }}">
    <div class="tr-card"><form method="POST" action="{{ route('team.update', $staffProfile) }}">@csrf @method('PUT') @include('tenant.team._form')<div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Guardar cambios</button><a class="btn btn-light" href="{{ route('team.show', $staffProfile) }}">Cancelar</a></div></form></div>
</x-layouts.tenant>
