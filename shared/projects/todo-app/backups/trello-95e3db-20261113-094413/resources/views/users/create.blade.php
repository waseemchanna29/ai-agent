@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Add user</h1>
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>

            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
            </select>

            <p style="margin-top:1rem;">
                <button type="submit" class="btn-primary">Create user</button>
            </p>
        </form>
        <p class="center"><a href="{{ route('users.index') }}">Back to users</a></p>
    </div>
@endsection
