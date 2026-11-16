@php $project = $project ?? null; @endphp

<form method="POST"
      action="{{ $project ? route('admin.projects.update', $project) : route('admin.projects.store') }}"
      class="space-y-4 bg-white shadow p-5 rounded-xl max-w-xl">
    @csrf
    @if ($project) @method('PUT') @endif

    <div>
        <label class="block mb-1 font-medium text-sm">Project For</label>
        <select name="project_for_id" required class="p-2 border rounded-lg w-full">
            <option value="">Select...</option>
            @foreach ($projectFors as $pf)
                <option value="{{ $pf->id }}" @selected(old('project_for_id', $project?->project_for_id) == $pf->id)>{{ $pf->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block mb-1 font-medium text-sm">Project name</label>
        <input type="text" name="name" value="{{ old('name', $project?->name) }}" required class="p-2 border rounded-lg w-full">
    </div>

    <div class="flex gap-3">
        <button class="bg-indigo-600 px-5 py-2 rounded-lg text-white">{{ $project ? 'Update' : 'Create' }}</button>
        <a href="{{ route('admin.projects.index') }}" class="px-5 py-2 text-gray-600">Cancel</a>
    </div>
</form>