<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with('projectFor:id,name')
            ->withCount('tasks')
            ->when($request->project_for_id, fn ($q, $id) => $q->where('project_for_id', $id))
            ->orderBy('name')->paginate(15)->withQueryString();

        $projectFors = ProjectFor::orderBy('name')->get(['id', 'name']);

        return view('admin.projects.index', compact('projects', 'projectFors'));
    }

    public function create()
    {
        return view('admin.projects.create', [
            'projectFors' => ProjectFor::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        Project::create($this->validated($request));

        return redirect()->route('admin.projects.index')->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        return view('admin.projects.edit', [
            'project'     => $project,
            'projectFors' => ProjectFor::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project);

        if ($project->worklogs()->exists() && (int) $data['project_for_id'] !== $project->project_for_id) {
            return back()->withInput()->withErrors([
                'project_for_id' => 'This project has worklogs, so its "Project For" cannot be changed.',
            ]);
        }

        $project->update($data);

        return redirect()->route('admin.projects.index')->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        if ($project->worklogs()->exists()) {
            return back()->with('error', "Cannot delete \"{$project->name}\": it is used in worklog entries.");
        }

        $project->delete();   // its tasks are removed by the database cascade

        return redirect()->route('admin.projects.index')->with('success', 'Project deleted.');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'project_for_id' => ['required', 'exists:project_fors,id'],
            'name'           => ['required', 'string', 'max:255',
                Rule::unique('projects', 'name')
                    ->where('project_for_id', $request->input('project_for_id'))
                    ->ignore($project?->id)],
        ]);
    }
}