@extends('layouts.app')

@section('content')
    <div class="card">
        <h1>Log in</h1>
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <label style="display:flex; align-items:center; gap:.4rem; margin-top:.75rem;">
                <input type="checkbox" name="remember" value="1" style="width:auto;">
                <span>Remember me</span>
            </label>

            <p style="margin-top:1rem;">
                <button type="submit" class="btn-primary">Log in</button>
            </p>
        </form>
        <p class="center">Need an account? <a href="{{ route('register') }}">Register</a></p>
    </div>
@endsection
