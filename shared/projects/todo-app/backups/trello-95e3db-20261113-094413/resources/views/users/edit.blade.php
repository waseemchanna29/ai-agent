@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Edit user</h1>
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')

            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autofocus>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password">
            <p class="muted" style="margin:.25rem 0 0;">Leave blank to keep the current password.</p>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation">

            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
            </select>
            <p class="muted" style="margin:.25rem 0 0;">Inactive users cannot log in.</p>

            <p style="margin-top:1rem;">
                <button type="submit" class="btn-primary">Save changes</button>
            </p>
        </form>
        <p class="center"><a href="{{ route('users.index') }}">Back to users</a></p>
    </div>
@endsection
