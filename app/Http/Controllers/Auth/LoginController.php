<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function showLogin(): Response
    {
        if (Auth::check()) {
            return Inertia::location(route('icu.dashboard'));
        }

        return Inertia::render('Auth/Login', [
            'flash' => ['error' => session('error')],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user) {
            return back()->with('error', 'Username atau password salah.');
        }

        if (! $user->is_active) {
            return back()->with('error', 'Akun Anda tidak aktif. Hubungi administrator.');
        }

        // Attempt login dengan username + password
        if (Auth::attempt(
            ['username' => $request->username, 'password' => $request->password],
            $request->boolean('remember')
        )) {
            $request->session()->regenerate();
            return redirect()->intended(route('icu.dashboard'));
        }

        return back()->with('error', 'Username atau password salah.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
