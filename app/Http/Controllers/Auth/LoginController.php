<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $data['username'], 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            ActivityLog::record('login_failed', 'Failed login attempt for "'.mb_substr($data['username'], 0, 60).'".');
            return back()->withErrors(['username' => 'Invalid credentials or inactive account.'])->onlyInput('username');
        }

        $request->session()->regenerate();
        ActivityLog::record('login', 'Logged in as '.Auth::user()->roleLabel().' ('.Auth::user()->username.').');

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            ActivityLog::record('logout', 'Logged out.');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
