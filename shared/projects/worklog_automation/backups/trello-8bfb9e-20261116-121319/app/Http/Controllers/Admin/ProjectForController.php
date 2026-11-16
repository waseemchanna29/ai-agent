<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectFor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectForController extends Controller
{
    public function index()
    {
        $projectFors = ProjectFor::withCount('projects')->orderBy('name')->paginate(15);

        return view('admin.project-fors.index', compact('projectFors'));
    }

    public function create()
    {
        return view('admin.project-fors.create');
    }

    public function store(Request $request)
    {
        ProjectFor::create($this->validated($request));

        return redirect()->route('admin.project-fors.index')->with('success', 'Project For created.');
    }

    public function edit(ProjectFor $projectFor)
    {
        return view('admin.project-fors.edit', compact('projectFor'));
    }

    public function update(Request $request, ProjectFor $projectFor)
    {
        $projectFor->update($this->validated($request, $projectFor));

        return redirect()->route('admin.project-fors.index')->with('success', 'Project For updated.');
    }

    public function destroy(ProjectFor $projectFor)
    {
        if ($projectFor->worklogs()->exists()) {
            return back()->with('error', "Cannot delete \"{$projectFor->name}\": it is used in worklog entries.");
        }

        // its projects and their tasks are removed by the database cascade
        $projectFor->delete();

        return redirect()->route('admin.project-fors.index')->with('success', 'Project For deleted.');
    }

    private function validated(Request $request, ?ProjectFor $projectFor = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('project_fors', 'name')->ignore($projectFor?->id)],
        ]);
    }
}