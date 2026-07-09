<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\KeycloakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(
        private readonly KeycloakService    $keycloak,
        private readonly ActivityLogService $activityLog,
    ) {}

    /** Halaman login — pass status Keycloak ke Vue untuk show/hide tombol SSO. */
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('icu.dashboard');
        }

        return Inertia::render('Auth/Login', [
            'flash'               => ['error' => session('error')],
            'keycloakAvailable'   => $this->keycloak->isReachable(),
            'keycloakRedirectUrl' => route('auth.keycloak'),
        ]);
    }

    /** Login lokal — fallback untuk admin atau saat Keycloak tidak bisa dijangkau. */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user || ! $user->is_active) {
            return back()->with('error', 'Username atau password salah.');
        }

        if ($user->isKeycloakUser()) {
            return back()->with('error', 'Akun ini terdaftar via SSO. Silakan login dengan tombol SSO.');
        }

        if (Auth::attempt(['username' => $request->username, 'password' => $request->password])) {
            $request->session()->regenerate();
            $request->session()->put('auth_via', 'local');
            $this->activityLog->loginLog();
            return redirect()->intended(route('icu.dashboard'));
        }

        return back()->with('error', 'Username atau password salah.');
    }
}
