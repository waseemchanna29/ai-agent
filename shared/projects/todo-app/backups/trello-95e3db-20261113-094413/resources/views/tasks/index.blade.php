@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>My tasks</h1>

        <form method="POST" action="{{ route('tasks.store') }}" class="add-form">
            @csrf
            <input type="text" name="title" value="{{ old('title') }}"
                   maxlength="255" placeholder="What needs to be done?" autofocus>
            <button type="submit" class="btn-primary">Add</button>
        </form>

        @if ($tasks->isEmpty())
            <p class="empty">No tasks yet. Add your first one above.</p>
        @else
            <ul class="tasks">
                @foreach ($tasks as $task)
                    <li>
                        <form method="POST" action="{{ route('tasks.update', $task) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="checkbox" onchange="this.form.submit()"
                                   @checked($task->is_completed)
                                   aria-label="Toggle complete">
                        </form>

                        <span class="task-title {{ $task->is_completed ? 'done' : '' }}">
                            {{ $task->title }}
                        </span>

                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline"
                              onsubmit="return confirm('Delete this task?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
