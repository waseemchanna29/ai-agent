<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->withCount('worklogs')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && $data['role'] !== Role::Admin->value) {
            return back()->withInput()
                ->withErrors(['role' => 'You cannot remove your own admin role.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);   // keep the current password
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->worklogs()->exists()) {
            return back()->with('error', "Cannot delete {$user->name}: this user has worklog entries.");
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'designation' => ['required', 'string', 'max:255'],
            'role'        => ['required', Rule::enum(Role::class)],
            'password'    => [
                // an admin must have a password; employees only need one to log in
                Rule::requiredIf(fn () => $request->input('role') === Role::Admin->value && ! $user?->password),
                'nullable', 'string', 'min:8',
            ],
        ]);
    }
}