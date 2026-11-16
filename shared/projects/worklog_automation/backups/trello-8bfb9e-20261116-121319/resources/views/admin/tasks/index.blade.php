@extends('layouts.admin')
@section('title', 'Tasks')

@section('content')
    <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3">
        <form method="GET" class="flex flex-wrap gap-2">
            <select name="project_id" onchange="this.form.submit()" class="p-2 border rounded-lg text-sm">
                <option value="">All projects</option>
                @foreach ($projects->groupBy(fn ($p) => $p->projectFor->name) as $group => $items)
                    <optgroup label="{{ $group }}">
                        @foreach ($items as $p)
                            <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search task"
                   class="p-2 border rounded-lg text-sm">
            <button class="bg-gray-800 px-3 rounded-lg text-white text-sm">Search</button>
        </form>
        <a href="{{ route('admin.tasks.create') }}" class="bg-indigo-600 px-4 py-2 rounded-lg text-white text-sm text-center">+ Add Task</a>
    </div>

    <div class="bg-white shadow rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="p-3">Task</th>
                    <th class="p-3">Project</th>
                    <th class="p-3">Worklogs</th>
                    <th class="p-3">Created by</th>
                    <th class="p-3">Updated by</th>
                    <th class="p-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tasks as $t)
                <tr class="border-t">
                    <td class="p-3">{{ $t->title }}</td>
                    <td class="p-3">{{ $t->project->projectFor->name }} / {{ $t->project->name }}</td>
                    <td class="p-3">{{ $t->worklogs_count }}</td>
                    <td class="p-3">{{ $t->creator?->name ?? '—' }}</td>
                    <td class="p-3">
                        {{ $t->updater?->name ?? '—' }}
                        <div class="text-gray-400 text-xs">{{ $t->updated_at->format('d M Y H:i') }}</div>
                    </td>
                    <td class="p-3">
                        <div class="flex justify-end gap-3">
                            <a href="{{ route('admin.tasks.edit', $t) }}" class="text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.tasks.destroy', $t) }}"
                                  onsubmit="return confirm('Delete this task?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-gray-400 text-center">No tasks found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $tasks->links() }}
@endsection