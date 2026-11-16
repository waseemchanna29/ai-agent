@extends('layouts.admin')
@section('title', 'Projects')

@section('content')
    <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3">
        <form method="GET" class="flex gap-2">
            <select name="project_for_id" onchange="this.form.submit()" class="p-2 border rounded-lg text-sm">
                <option value="">All Project For</option>
                @foreach ($projectFors as $pf)
                    <option value="{{ $pf->id }}" @selected(request('project_for_id') == $pf->id)>{{ $pf->name }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.projects.create') }}" class="bg-indigo-600 px-4 py-2 rounded-lg text-white text-sm text-center">+ Add Project</a>
    </div>

    <div class="bg-white shadow rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="p-3">Project</th><th class="p-3">Project For</th>
                    <th class="p-3">Tasks</th><th class="p-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($projects as $p)
                <tr class="border-t">
                    <td class="p-3">{{ $p->name }}</td>
                    <td class="p-3">{{ $p->projectFor->name }}</td>
                    <td class="p-3">{{ $p->tasks_count }}</td>
                    <td class="p-3">
                        <div class="flex justify-end gap-3">
                            <a href="{{ route('admin.projects.edit', $p) }}" class="text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.projects.destroy', $p) }}"
                                  onsubmit="return confirm('Delete this project and its tasks?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-4 text-gray-400 text-center">No projects found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $projects->links() }}
@endsection