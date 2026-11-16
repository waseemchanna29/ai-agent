<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Worklog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="bg-gray-100 text-gray-800" x-data="{ open: false }">

@php
    $nav = [
        ['Dashboard',   'dashboard',                'dashboard',            false],
        ['Users',       'admin.users.index',        'admin.users.*',        true],
        ['Project For', 'admin.project-fors.index', 'admin.project-fors.*', true],
        ['Projects',    'admin.projects.index',     'admin.projects.*',     true],
        ['Tasks', 'admin.tasks.index', 'admin.tasks.*', true],
    ];
@endphp

<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="left-0 z-30 md:static fixed inset-y-0 flex flex-col bg-gray-900 p-4 w-64 text-gray-200 transition-transform md:translate-x-0 transform"
           :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="mb-6 px-2 font-bold text-white text-lg">Worklog</div>

        <nav class="flex-1 space-y-1">
            @foreach ($nav as [$label, $route, $pattern, $adminOnly])
                @continue($adminOnly && ! auth()->user()->isAdmin())
                <a href="{{ route($route) }}"
                   class="block rounded-lg px-3 py-2 text-sm {{ request()->routeIs($pattern) ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <a href="{{url('/worklog/'.auth()->user()->uuid) }}" class="px-3 py-2 text-gray-400 hover:text-white text-sm">← Worklog form</a>
    </aside>

    {{-- Backdrop for mobile --}}
    <div x-show="open" x-cloak @click="open = false" class="md:hidden z-20 fixed inset-0 bg-black/40"></div>

    {{-- Main --}}
    <div class="flex flex-col flex-1 min-w-0">
        <header class="flex justify-between items-center bg-white shadow-sm px-4 py-3">
            <div class="flex items-center gap-3">
                <button type="button" @click="open = !open" class="md:hidden text-2xl leading-none">☰</button>
                <h1 class="font-semibold text-lg">@yield('title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden sm:block text-right">
                    <div class="font-medium text-sm">{{ auth()->user()->name }}</div>
                    <div class="text-gray-500 text-xs">{{ auth()->user()->email }}</div>
                </div>
                <span class="text-xs rounded px-2 py-0.5 {{ auth()->user()->isAdmin() ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ auth()->user()->role->label() }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="bg-gray-800 px-3 py-1.5 rounded-lg text-white text-sm">Logout</button>
                </form>
            </div>
        </header>

        <main class="space-y-4 p-4 sm:p-6">
            @if (session('success'))
                <div class="bg-green-100 p-3 rounded-lg text-green-800 text-sm">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-100 p-3 rounded-lg text-red-800 text-sm">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-100 p-3 rounded-lg text-red-800 text-sm">
                    <ul class="pl-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

</body>
</html>