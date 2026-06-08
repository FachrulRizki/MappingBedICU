<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function showLogin(): Response
    {
        // Redirect jika sudah login
        if (Auth::check()) {
            return Inertia::location(route('icu.dashboard'));
        }

        return Inertia::render('Auth/Login', [
            'flash' => ['error' => session('error')],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Cek user aktif
        $user = \App\Models\User::where('email', $credentials['email'])->first();
        if ($user && ! $user->is_active) {
            return back()->with('error', 'Akun Anda tidak aktif. Hubungi administrator.');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Simpan info user ke session untuk dipakai controller
            session(['user_name' => Auth::user()->name]);

            return redirect()->intended(route('icu.dashboard'));
        }

        return back()->with('error', 'Email atau password salah.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
