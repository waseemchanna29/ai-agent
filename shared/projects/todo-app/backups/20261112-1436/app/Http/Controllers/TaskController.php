<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    /**
     * Show the current user's tasks (newest first).
     */
    public function index(Request $request): View
    {
        $tasks = $request->user()
            ->tasks()
            ->latest()
            ->get();

        return view('tasks.index', ['tasks' => $tasks]);
    }

    /**
     * Store a new task for the current user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->tasks()->create([
            'title' => $validated['title'],
            'is_completed' => false,
        ]);

        return redirect()
            ->route('tasks.index')
            ->with('status', 'Task added.');
    }

    /**
     * Toggle the completion state of a task the user owns.
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeOwnership($request, $task);

        $task->update([
            'is_completed' => ! $task->is_completed,
        ]);

        return redirect()
            ->route('tasks.index')
            ->with('status', 'Task updated.');
    }

    /**
     * Delete a task the user owns.
     */
    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeOwnership($request, $task);

        $task->delete();

        return redirect()
            ->route('tasks.index')
            ->with('status', 'Task deleted.');
    }

    /**
     * Abort with 404 if the task does not belong to the current user.
     */
    private function authorizeOwnership(Request $request, Task $task): void
    {
        abort_if($task->user_id !== $request->user()->id, 404);
    }
}
