@php $task = $task ?? null; @endphp

<form method="POST"
      action="{{ $task ? route('admin.tasks.update', $task) : route('admin.tasks.store') }}"
      class="space-y-4 bg-white shadow p-5 rounded-xl max-w-xl">
    @csrf
    @if ($task) @method('PUT') @endif

    <div>
        <label class="block mb-1 font-medium text-sm">Project</label>
        <select name="project_id" required class="p-2 border rounded-lg w-full">
            <option value="">Select...</option>
            @foreach ($projects->groupBy(fn ($p) => $p->projectFor->name) as $group => $items)
                <optgroup label="{{ $group }}">
                    @foreach ($items as $p)
                        <option value="{{ $p->id }}" @selected(old('project_id', $task?->project_id) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block mb-1 font-medium text-sm">Task title</label>
        <input type="text" name="title" value="{{ old('title', $task?->title) }}" required class="p-2 border rounded-lg w-full">
    </div>

    @if ($task)
        <div class="space-y-1 pt-3 border-t text-gray-500 text-xs">
            <div>Created by <strong>{{ $task->creator?->name ?? '—' }}</strong> on {{ $task->created_at->format('d M Y H:i') }}</div>
            <div>Last updated by <strong>{{ $task->updater?->name ?? '—' }}</strong> on {{ $task->updated_at->format('d M Y H:i') }}</div>
        </div>
    @endif

    <div class="flex gap-3">
        <button class="bg-indigo-600 px-5 py-2 rounded-lg text-white">{{ $task ? 'Update' : 'Create' }}</button>
        <a href="{{ route('admin.tasks.index') }}" class="px-5 py-2 text-gray-600">Cancel</a>
    </div>
</form>