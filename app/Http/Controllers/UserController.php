<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::with('roles')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['is_active' => true, 'must_change_password' => true]), 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', Password::min(10)->letters()->numbers()],
            'roles' => 'array', 'roles.*' => 'exists:roles,id',
        ]);
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            'is_active' => true, 'must_change_password' => $request->boolean('must_change_password', true),
        ]);
        $user->roles()->sync($data['roles'] ?? []);
        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user->load('roles'), 'roles' => Role::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(10)->letters()->numbers()],
            'roles' => 'array', 'roles.*' => 'exists:roles,id',
        ]);
        $active = $request->boolean('is_active');
        $roleIds = collect($data['roles'] ?? [])->map(fn ($v) => (int) $v);
        $adminId = Role::where('code', 'ADMIN')->value('id');

        // Never leave the system without a working administrator.
        $stillAdmin = $active && $roleIds->contains($adminId);
        if ($user->isAdmin() && $user->is_active && ! $stillAdmin) {
            $others = User::where('id', '!=', $user->id)->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('code', 'ADMIN'))->count();
            if ($others === 0) {
                throw ValidationException::withMessages(['roles' => 'This is the only active administrator. Make another user an administrator first.']);
            }
        }

        $fill = ['name' => $data['name'], 'email' => $data['email'], 'is_active' => $active];
        if (! empty($data['password'])) {
            $fill['password'] = $data['password'];
            $fill['must_change_password'] = $request->boolean('must_change_password', true);
        }
        $before = $user->roles->pluck('code')->sort()->values()->all();
        $user->update($fill);
        $user->roles()->sync($roleIds->all());
        $after = $user->roles()->pluck('code')->sort()->values()->all();
        if ($before !== $after) {
            app(\App\Services\Audit\Audit::class)->record('UPDATED', 'User', (string) $user->id, $user->email,
                ['roles' => ['from' => implode(', ', $before), 'to' => implode(', ', $after)]]);
        }
        return redirect()->route('users.index')->with('success', 'User saved.');
    }
}
