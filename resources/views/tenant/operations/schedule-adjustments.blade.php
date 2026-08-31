<x-layouts.tenant title="Histórico de ajustes" subtitle="Cambios posteriores al horario aprobado">
    <div class="tr-card">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Fecha afectada</th>
                        <th>Colaborador</th>
                        <th>Turno anterior</th>
                        <th>Nuevo turno</th>
                        <th>Motivo</th>
                        <th>Registrado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->date->format('d/m/Y') }}</td>
                            <td>{{ $item->core_user_id }}</td>
                            <td>{{ $item->previous_shift_id ?? 'Sin asignación' }}</td>
                            <td>{{ $item->new_shift_id ?? 'Sin asignación' }}</td>
                            <td>{{ $item->reason }}</td>
                            <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted">No hay ajustes registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $items->links() }}
    </div>
</x-layouts.tenant>
