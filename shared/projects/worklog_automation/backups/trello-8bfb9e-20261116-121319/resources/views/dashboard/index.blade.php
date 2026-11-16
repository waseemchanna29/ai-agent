@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
    @if ($stats)
        <div class="gap-4 grid grid-cols-2 lg:grid-cols-4">
            @foreach ($stats as $label => $value)
                <div class="bg-white shadow p-4 rounded-xl">
                    <div class="text-gray-500 text-sm">{{ $label }}</div>
                    <div class="font-bold text-2xl">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="bg-white shadow rounded-xl overflow-x-auto">
        <div class="p-4 border-b font-semibold">
            {{ auth()->user()->isAdmin() ? 'Recent worklogs' : 'My recent worklogs' }}
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="p-3">Date</th>
                    @if (auth()->user()->isAdmin()) <th class="p-3">User</th> @endif
                    <th class="p-3">Project</th>
                    <th class="p-3">Task</th>
                    <th class="p-3">Hours</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($recent as $w)
                <tr class="border-t">
                    <td class="p-3">{{ $w->work_date->format('d M Y') }}</td>
                    @if (auth()->user()->isAdmin()) <td class="p-3">{{ $w->user->name }}</td> @endif
                    <td class="p-3">{{ $w->projectFor->name }} / {{ $w->project->name }}</td>
                    <td class="p-3">{{ $w->task->title }}</td>
                    <td class="p-3">{{ $w->hours_spent }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-gray-400 text-center">No worklogs yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection