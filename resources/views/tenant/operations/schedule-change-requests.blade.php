<x-layouts.tenant title="Autorizaciones históricas">
    <div class="tr-card">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead><tr><th>Horario</th><th>Motivo</th><th>Estado</th><th>Solicitada</th><th>Acción</th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>#{{ $item->schedule_period_id }}</td>
                            <td>{{ $item->reason }}</td>
                            <td>{{ ['pending' => 'Pendiente', 'approved' => 'Aprobada', 'rejected' => 'Rechazada'][$item->status] }}</td>
                            <td>{{ ($item->requested_at ?? $item->created_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                @if (($scope['is_account_administrator'] ?? false) || $scope['role'] === 'management')
                                    @if ($item->status === 'pending')
                                        <form method="POST" action="{{ route('operations.schedule-change-requests.resolve', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status"><option value="approved">Aprobar</option><option value="rejected">Rechazar</option></select>
                                            <input name="review_comment" placeholder="Observación">
                                            <button class="btn btn-sm btn-primary">Resolver</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No hay solicitudes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links() }}
    </div>
</x-layouts.tenant>
