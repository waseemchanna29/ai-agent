@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Users</h1>

        <p style="margin:0 0 1.25rem;">
            <a href="{{ route('users.create') }}" class="btn-primary"
               style="display:inline-block; padding:.55rem .9rem; border-radius:7px;">Add user</a>
        </p>

        @if ($users->isEmpty())
            <p class="empty">No users yet. Add the first one above.</p>
        @else
            <ul class="tasks">
                @foreach ($users as $u)
                    <li>
                        <span class="task-title">
                            {{ $u->name }}
                            <span class="muted">&middot; {{ $u->email }}</span>
                        </span>

                        <a href="{{ route('users.edit', $u) }}" class="btn-link">Edit</a>

                        <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline"
                              onsubmit="return confirm('Delete this user?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($users->hasPages())
            <div style="margin-top:1rem;">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
