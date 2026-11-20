<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('event:id,name')->withCount('judgedEvents')->orderBy('role')->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['role' => 'judge', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $user = User::create($this->validated($request));
        ActivityLog::record('account', 'Created '.$user->roleLabel().' account '.$user->username.'.');

        return redirect()->route('admin.users.index')->with('success', 'Account created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($user->is(auth()->user())) {
            // Never lock yourself out.
            $data['role'] = User::ROLE_ADMIN;
            $data['is_active'] = true;
        }
        $user->update($data);
        ActivityLog::record('account', 'Updated account '.$user->username.'.');

        return redirect()->route('admin.users.index')->with('success', 'Account updated.');
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $user->delete();
        ActivityLog::record('account', 'Deleted account '.$user->username.' ('.$user->name.').');

        return back()->with('success', 'Account deleted.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        // Only tabulators are tied to an event.
        $data['event_id'] = $data['role'] === User::ROLE_TABULATOR ? ($data['event_id'] ?? null) : null;

        return $data;
    }
}
