<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="flex justify-center items-center bg-gray-100 px-4 min-h-screen">

<div class="bg-white shadow p-6 rounded-xl w-full max-w-sm">
    <h1 class="mb-1 font-bold text-xl">Admin Login</h1>
    <p class="mb-5 text-gray-500 text-sm">Dashboard access ke liye login karein.</p>

    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1 font-medium text-sm">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="p-2 border rounded-lg w-full">
            @error('email')
                <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div x-data="{ show: false }">
    <label class="block mb-1 font-medium text-sm">Password</label>
    <div class="relative">
        <input :type="show ? 'text' : 'password'" name="password" required
               autocomplete="current-password"
               class="p-2 pr-10 border rounded-lg w-full">

        <button type="button" @click="show = !show"
                :aria-label="show ? 'Hide password' : 'Show password'"
                class="top-1/2 right-2 absolute text-gray-500 hover:text-gray-700 -translate-y-1/2">
            {{-- eye (password hidden, click to show) --}}
            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{-- eye-off (password visible, click to hide) --}}
            <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
        </button>
    </div>
</div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>

        <button type="submit" class="bg-indigo-600 py-2 rounded-lg w-full text-white">Login</button>
    </form>

</div>

</body>
</html>