@php $projectFor = $projectFor ?? null; @endphp

<form method="POST"
      action="{{ $projectFor ? route('admin.project-fors.update', $projectFor) : route('admin.project-fors.store') }}"
      class="space-y-4 bg-white shadow p-5 rounded-xl max-w-xl">
    @csrf
    @if ($projectFor) @method('PUT') @endif

    <div>
        <label class="block mb-1 font-medium text-sm">Name</label>
        <input type="text" name="name" value="{{ old('name', $projectFor?->name) }}" required class="p-2 border rounded-lg w-full">
    </div>

    <div class="flex gap-3">
        <button class="bg-indigo-600 px-5 py-2 rounded-lg text-white">{{ $projectFor ? 'Update' : 'Create' }}</button>
        <a href="{{ route('admin.project-fors.index') }}" class="px-5 py-2 text-gray-600">Cancel</a>
    </div>
</form>