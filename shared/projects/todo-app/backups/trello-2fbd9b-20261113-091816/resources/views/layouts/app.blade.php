<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'School To-Do') }}</title>
    <style>
        :root { --bg:#f4f5f7; --card:#fff; --ink:#1f2933; --muted:#6b7280; --line:#e5e7eb;
                --primary:#2563eb; --danger:#dc2626; --ok:#059669; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               background:var(--bg); color:var(--ink); }
        a { color:var(--primary); text-decoration:none; }
        a:hover { text-decoration:underline; }
        nav { display:flex; align-items:center; justify-content:space-between;
              background:var(--card); border-bottom:1px solid var(--line); padding:.75rem 1.25rem; }
        nav .brand { font-weight:700; font-size:1.05rem; }
        nav .user { color:var(--muted); display:flex; align-items:center; gap:.75rem; }
        main { max-width:640px; margin:2rem auto; padding:0 1rem; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px;
                padding:1.5rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        h1 { margin:0 0 1rem; font-size:1.4rem; }
        label { display:block; margin:.6rem 0 .25rem; font-size:.9rem; color:var(--muted); }
        input[type=text], input[type=email], input[type=password] {
            width:100%; padding:.55rem .7rem; border:1px solid var(--line);
            border-radius:7px; font-size:1rem; }
        input:focus { outline:2px solid var(--primary); outline-offset:0; }
        button { cursor:pointer; border:none; border-radius:7px; font-size:.95rem;
                 padding:.55rem .9rem; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-link { background:none; color:var(--muted); padding:.25rem .4rem; }
        .btn-danger { background:none; color:var(--danger); padding:.25rem .4rem; }
        .flash { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;
                 padding:.6rem .9rem; border-radius:7px; margin-bottom:1rem; font-size:.9rem; }
        .errors { background:#fef2f2; border:1px solid #fecaca; color:#991b1b;
                  padding:.6rem .9rem; border-radius:7px; margin-bottom:1rem; font-size:.9rem; }
        .errors ul { margin:.2rem 0 0 1rem; padding:0; }
        .add-form { display:flex; gap:.5rem; margin-bottom:1.25rem; }
        .add-form input { flex:1; }
        ul.tasks { list-style:none; margin:0; padding:0; }
        ul.tasks li { display:flex; align-items:center; gap:.6rem;
                      padding:.6rem .2rem; border-bottom:1px solid var(--line); }
        ul.tasks li:last-child { border-bottom:none; }
        .task-title { flex:1; }
        .task-title.done { text-decoration:line-through; color:var(--muted); }
        .empty { color:var(--muted); text-align:center; padding:1.5rem 0; }
        .muted { color:var(--muted); font-size:.9rem; }
        .inline { display:inline; }
        .center { text-align:center; margin-top:1rem; font-size:.9rem; }
    </style>
</head>
<body>
    @auth
        <nav>
            <span class="brand">{{ config('app.name', 'School To-Do') }}</span>
            <span class="user">
                <a href="{{ route('users.index') }}">Users</a>
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn-link">Log out</button>
                </form>
            </span>
        </nav>
    @endauth

    <main>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <strong>Please fix the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
