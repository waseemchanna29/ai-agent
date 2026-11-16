<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /* ---------- Worklog form: "+ Add task" button (JSON) ---------- */

    public function quickStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title'      => ['required', 'string', 'max:255'],
            'user_id'    => ['required', 'exists:users,id'],
        ]);

        // Reuse the task if it already exists, and leave its audit fields alone
        $task = Task::firstOrCreate(
            ['project_id' => $data['project_id'], 'title' => trim($data['title'])],
            ['created_by' => $data['user_id'], 'updated_by' => $data['user_id']]
        );

        return response()->json($task->only('id', 'project_id', 'title'), 201);
    }

    /* ---------- Admin CRUD (admin role only, see routes) ---------- */

    public function index(Request $request)
    {
        $tasks = Task::with(['project.projectFor:id,name', 'creator:id,name', 'updater:id,name'])
            ->withCount('worklogs')
            ->when($request->project_id, fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->q, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest('updated_at')->paginate(15)->withQueryString();

        return view('admin.tasks.index', [
            'tasks'    => $tasks,
            'projects' => $this->projects(),
        ]);
    }

    public function create()
    {
        return view('admin.tasks.create', ['projects' => $this->projects()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Task::create($data + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.tasks.index')->with('success', 'Task created.');
    }

    public function edit(Task $task)
    {
        return view('admin.tasks.edit', [
            'task'     => $task->load(['creator:id,name', 'updater:id,name']),
            'projects' => $this->projects(),
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $data = $this->validated($request, $task);

        // Worklogs store project_id too, so moving a used task would make them inconsistent
        if ($task->worklogs()->exists() && (int) $data['project_id'] !== $task->project_id) {
            return back()->withInput()->withErrors([
                'project_id' => 'This task has worklogs, so its project cannot be changed.',
            ]);
        }

        $task->update($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('admin.tasks.index')->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        if ($task->worklogs()->exists()) {
            return back()->with('error', "Cannot delete \"{$task->title}\": it is used in worklog entries.");
        }

        $task->delete();

        return redirect()->route('admin.tasks.index')->with('success', 'Task deleted.');
    }

    private function projects()
    {
        return Project::with('projectFor:id,name')->orderBy('name')->get(['id', 'project_for_id', 'name']);
    }

    private function validated(Request $request, ?Task $task = null): array
    {
        $request->merge(['title' => trim((string) $request->input('title'))]);

        return $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title'      => ['required', 'string', 'max:255',
                Rule::unique('tasks', 'title')
                    ->where('project_id', $request->input('project_id'))
                    ->ignore($task?->id)],
        ]);
    }
}