@extends('layouts.admin')
@section('title', 'Project For')

@section('content')
    <div class="flex justify-end">
        <a href="{{ route('admin.project-fors.create') }}" class="bg-indigo-600 px-4 py-2 rounded-lg text-white text-sm">+ Add Project For</a>
    </div>

    <div class="bg-white shadow rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr><th class="p-3">Name</th><th class="p-3">Projects</th><th class="p-3 text-right">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($projectFors as $pf)
                <tr class="border-t">
                    <td class="p-3">{{ $pf->name }}</td>
                    <td class="p-3">{{ $pf->projects_count }}</td>
                    <td class="p-3">
                        <div class="flex justify-end gap-3">
                            <a href="{{ route('admin.project-fors.edit', $pf) }}" class="text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.project-fors.destroy', $pf) }}"
                                  onsubmit="return confirm('Delete this Project For and all its projects and tasks?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-4 text-gray-400 text-center">Nothing here yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $projectFors->links() }}
@endsection