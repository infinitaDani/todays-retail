<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChecklistRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Modules\Operations\Models\Shift;
use App\Modules\Tasks\Models\Checklist;
use App\Modules\Tasks\Models\ChecklistItem;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskExecution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TasksController extends Controller
{
    public function tasks(Request $request): View
    {
        $query = Task::query()->withCount('checklistItems')
            ->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('name', 'like', '%'.$request->string('search').'%')->orWhere('description', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')));

        return view('tenant.tasks.tasks', ['tasks' => $query->orderBy('name')->paginate(10)->withQueryString(), 'summary' => $this->taskSummary()]);
    }

    public function createTask(): View { return view('tenant.tasks.create'); }
    public function storeTask(StoreTaskRequest $request): RedirectResponse { $task = Task::create($request->validated()); return redirect()->route('tasks.show', $task)->with('success', 'Tarea creada correctamente.'); }
    public function showTask(Task $task): View { return view('tenant.tasks.show', ['task' => $task, 'inUse' => $this->taskHasHistoryOrUse($task)]); }
    public function editTask(Task $task): View { return view('tenant.tasks.edit', compact('task')); }
    public function updateTask(StoreTaskRequest $request, Task $task): RedirectResponse { $task->update($request->validated()); return redirect()->route('tasks.show', $task)->with('success', 'Tarea actualizada correctamente.'); }
    public function toggleTask(Task $task): RedirectResponse { $task->update(['status' => $task->status === 'active' ? 'inactive' : 'active']); return back()->with('success', 'Estado de la tarea actualizado.'); }

    public function destroyTask(Task $task): RedirectResponse
    {
        if ($this->taskHasHistoryOrUse($task)) {
            return back()->withErrors(['task' => 'Esta tarea está en uso o tiene historial. Desactívala en lugar de eliminarla.']);
        }
        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'Tarea eliminada.');
    }

    public function checklists(Request $request): View
    {
        $query = Checklist::query()->with('shifts')->withCount(['items', 'executions'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('shift_id'), fn (Builder $query) => $query->whereHas('shifts', fn (Builder $shifts) => $shifts->whereKey($request->integer('shift_id'))))
            ->when($request->input('usage') === 'used', fn (Builder $query) => $query->has('executions'))
            ->when($request->input('usage') === 'unused', fn (Builder $query) => $query->doesntHave('executions'));

        return view('tenant.tasks.checklists', [
            'checklists' => $query->orderBy('name')->paginate(10)->withQueryString(),
            'shifts' => $this->activeShifts(),
            'summary' => ['total' => Checklist::count(), 'active' => Checklist::where('status', 'active')->count(), 'inactive' => Checklist::where('status', 'inactive')->count(), 'used' => Checklist::has('executions')->count()],
        ]);
    }

    public function createChecklist(): View { return view('tenant.tasks.checklists-form', $this->checklistFormData()); }

    public function storeChecklist(StoreChecklistRequest $request): RedirectResponse
    {
        $checklist = DB::connection('tenant')->transaction(function () use ($request): Checklist {
            $data = $request->validated();
            $checklist = Checklist::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'shift_id' => $data['shift_ids'][0], 'status' => $data['status']]);
            $this->syncChecklist($checklist, $data['shift_ids'], $data['items'] ?? []);
            return $checklist;
        });
        return redirect()->route('checklists.show', $checklist)->with('success', 'Checklist creado correctamente.');
    }

    public function showChecklist(Checklist $checklist): View
    {
        $checklist->load(['shifts', 'items.task']);
        return view('tenant.tasks.checklists-show', ['checklist' => $checklist, 'inUse' => $this->checklistHasHistoryOrUse($checklist)]);
    }

    public function editChecklist(Checklist $checklist): View
    {
        $checklist->load(['shifts', 'items.task']);
        return view('tenant.tasks.checklists-form', array_merge($this->checklistFormData(), compact('checklist')));
    }

    public function updateChecklist(StoreChecklistRequest $request, Checklist $checklist): RedirectResponse
    {
        DB::connection('tenant')->transaction(function () use ($request, $checklist): void {
            $data = $request->validated();
            $checklist->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'shift_id' => $data['shift_ids'][0], 'status' => $data['status']]);
            $this->syncChecklist($checklist, $data['shift_ids'], $data['items'] ?? []);
        });
        return redirect()->route('checklists.show', $checklist)->with('success', 'Checklist actualizado correctamente.');
    }

    public function toggleChecklist(Checklist $checklist): RedirectResponse { $checklist->update(['status' => $checklist->status === 'active' ? 'inactive' : 'active']); return back()->with('success', 'Estado del checklist actualizado.'); }

    public function destroyChecklist(Checklist $checklist): RedirectResponse
    {
        if ($this->checklistHasHistoryOrUse($checklist)) {
            return back()->withErrors(['checklist' => 'Este checklist ya tiene historial o está en uso. Crea un nuevo checklist y desactiva este.']);
        }
        $checklist->delete();
        return redirect()->route('checklists.index')->with('success', 'Checklist eliminado.');
    }

    public function reorderChecklistItems(Request $request, Checklist $checklist): RedirectResponse
    {
        $data = $request->validate(['item_ids' => ['required', 'array'], 'item_ids.*' => ['integer', 'distinct']]);
        $items = $checklist->items()->whereIn('id', $data['item_ids'])->get()->keyBy('id');
        if ($items->count() !== count($data['item_ids'])) { throw ValidationException::withMessages(['item_ids' => 'La lista contiene tareas inválidas.']); }
        foreach ($data['item_ids'] as $index => $itemId) { $items[$itemId]->update(['sort_order' => $index + 1]); }
        return back()->with('success', 'Orden de tareas actualizado.');
    }

    private function syncChecklist(Checklist $checklist, array $shiftIds, array $items): void
    {
        $checklist->shifts()->sync($shiftIds);
        $existing = $checklist->items()->get()->keyBy('id');
        $incomingIds = [];
        foreach ($items as $order => $item) {
            $itemId = $item['id'] ?? null;
            if ($itemId && $existing->has($itemId)) {
                $existing[$itemId]->update(['task_id' => $item['task_id'], 'start_time' => $item['start_time'] ?? null, 'due_time' => $item['due_time'] ?? null, 'sort_order' => $order + 1]);
                $incomingIds[] = $itemId;
            } else {
                $created = $checklist->items()->create(['task_id' => $item['task_id'], 'start_time' => $item['start_time'] ?? null, 'due_time' => $item['due_time'] ?? null, 'sort_order' => $order + 1]);
                $incomingIds[] = $created->id;
            }
        }
        $removedIds = $existing->keys()->diff($incomingIds)->all();
        if ($removedIds !== [] && TaskExecution::query()->whereIn('checklist_item_id', $removedIds)->exists()) {
            throw ValidationException::withMessages(['items' => 'No puedes eliminar una tarea que ya tiene historial de ejecución. Crea un nuevo checklist y desactiva este.']);
        }
        $checklist->items()->whereIn('id', $removedIds)->delete();
    }

    private function taskSummary(): array { return ['total' => Task::count(), 'active' => Task::where('status', 'active')->count(), 'inactive' => Task::where('status', 'inactive')->count(), 'in_checklists' => Task::has('checklistItems')->count()]; }
    private function activeShifts() { return Shift::query()->where('status', 'active')->orderBy('name')->get(); }
    private function checklistFormData(): array { return ['shifts' => $this->activeShifts(), 'tasks' => Task::query()->orderBy('name')->get()]; }
    private function taskHasHistoryOrUse(Task $task): bool { return $task->checklistItems()->exists() || TaskExecution::query()->whereHas('checklistItem', fn (Builder $items) => $items->where('task_id', $task->id))->exists(); }
    private function checklistHasHistoryOrUse(Checklist $checklist): bool { return $checklist->executions()->exists() || TaskExecution::query()->whereHas('checklistExecution', fn (Builder $executions) => $executions->where('checklist_id', $checklist->id))->exists(); }
}
