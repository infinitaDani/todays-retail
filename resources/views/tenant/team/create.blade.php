<x-layouts.tenant title="Nuevo colaborador" subtitle="Crea o vincula una persona a la cuenta activa">
    <div class="tr-card"><form method="POST" action="{{ route('team.store') }}">@csrf @include('tenant.team._form')<div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Guardar colaborador</button><a class="btn btn-light" href="{{ route('team.index') }}">Cancelar</a></div></form></div>
</x-layouts.tenant>
