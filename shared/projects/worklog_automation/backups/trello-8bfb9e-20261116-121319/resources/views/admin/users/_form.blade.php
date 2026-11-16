@php $user = $user ?? null; @endphp

<form method="POST"
      action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}"
      class="gap-4 grid grid-cols-1 sm:grid-cols-2 bg-white shadow p-5 rounded-xl max-w-3xl">
    @csrf
    @if ($user) @method('PUT') @endif

    <div>
        <label class="block mb-1 font-medium text-sm">Name</label>
        <input type="text" name="name" value="{{ old('name', $user?->name) }}" required class="p-2 border rounded-lg w-full">
    </div>
    <div>
        <label class="block mb-1 font-medium text-sm">Email</label>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}" required class="p-2 border rounded-lg w-full">
    </div>
    <div>
        <label class="block mb-1 font-medium text-sm">Designation</label>
        <input type="text" name="designation" value="{{ old('designation', $user?->designation) }}" required class="p-2 border rounded-lg w-full">
    </div>
    <div>
        <label class="block mb-1 font-medium text-sm">Role</label>
        <select name="role" required class="p-2 border rounded-lg w-full">
            @foreach (\App\Enums\Role::cases() as $r)
                <option value="{{ $r->value }}" @selected(old('role', $user?->role?->value ?? 'employee') === $r->value)>
                    {{ $r->label() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-2" x-data="{ show: false }">
    <label class="block mb-1 font-medium text-sm">
        Password
        <span class="font-normal text-gray-400">
            {{ $user ? '(leave blank to keep the current one)' : '(required for admins; optional for employees, needed only to log in)' }}
        </span>
    </label>
    <div class="relative">
        <input :type="show ? 'text' : 'password'" name="password" autocomplete="new-password"
               class="p-2 pr-10 border rounded-lg w-full">

        <button type="button" @click="show = !show"
                :aria-label="show ? 'Hide password' : 'Show password'"
                class="top-1/2 right-2 absolute text-gray-500 hover:text-gray-700 -translate-y-1/2">
            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
        </button>
    </div>
</div>

    <div class="flex gap-3 sm:col-span-2">
        <button class="bg-indigo-600 px-5 py-2 rounded-lg text-white">{{ $user ? 'Update' : 'Create' }}</button>
        <a href="{{ route('admin.users.index') }}" class="px-5 py-2 text-gray-600">Cancel</a>
    </div>
</form>