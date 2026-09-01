<x-layouts.tenant
    title="Editar tarea"
    :subtitle="$task->name"
>
    <div class="tr-card">
        <form
            method="POST"
            action="{{ route('tasks.update', $task) }}"
        >
            @csrf
            @method('PUT')

            @include('tenant.tasks._task-form')

            <div class="d-flex gap-2 mt-4">
                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Guardar cambios
                </button>

                <a
                    class="btn btn-light"
                    href="{{ route('tasks.show', $task) }}"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.tenant>