@extends('layouts.admin')
@section('title', 'Users')

@section('content')
    <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3">
        <form method="GET" class="flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email"
                class="p-2 border rounded-lg text-sm">
            <button class="bg-gray-800 px-3 rounded-lg text-white text-sm">Search</button>
        </form>
        <a href="{{ route('admin.users.create') }}"
            class="bg-indigo-600 px-4 py-2 rounded-lg text-white text-sm text-center">+ Add User</a>
    </div>

    <div class="bg-white shadow rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="p-3">Worklog link</th>
                    <th class="p-3">Name</th>
                    <th class="p-3">Email</th>
                    <th class="p-3">Designation</th>
                    <th class="p-3">Role</th>
                    <th class="p-3">Worklogs</th>
                    <th class="p-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr class="border-t">
                        <td class="p-3" x-data="{ copied: false }">
                            @if ($u->uuid)
                                @php $link = route('worklog.create', $u->uuid); @endphp
                                <div class="flex items-center gap-2">
                                    <a href="{{ $link }}" target="_blank" rel="noopener"
                                        class="text-blue-600 hover:underline">Open</a>
                                    <<button type="button"
                                        @click="
            const t = '{{ $link }}';
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(t);
            } else {
                const a = document.createElement('textarea');
                a.value = t;
                a.style.position = 'fixed';
                a.style.opacity = '0';
                document.body.appendChild(a);
                a.select();
                document.execCommand('copy');
                a.remove();
            }
            copied = true;
            setTimeout(() => copied = false, 1500)
        "
                                        class="text-gray-600 hover:underline">
                                        <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                                        </button>
                                </div>
                                <div class="max-w-[220px] text-gray-400 text-xs truncate" title="{{ $link }}">
                                    {{ $link }}</div>
                            @else
                                <span class="text-gray-400">No UUID</span>
                            @endif
                        </td>
                        <td class="p-3">{{ $u->name }}</td>
                        <td class="p-3">{{ $u->email }}</td>
                        <td class="p-3">{{ $u->designation }}</td>
                        <td class="p-3">
                            <span
                                class="text-xs rounded px-2 py-0.5 {{ $u->isAdmin() ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $u->role->label() }}
                            </span>
                        </td>
                        <td class="p-3">{{ $u->worklogs_count }}</td>
                        <td class="p-3">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $u) }}"
                                    class="text-blue-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                    onsubmit="return confirm('Delete this user?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-4 text-gray-400 text-center">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
