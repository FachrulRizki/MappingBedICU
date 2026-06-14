<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\KeycloakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LoginController — login lokal (username + password).
 *
 * Tetap aktif sebagai fallback ketika:
 *   - Keycloak tidak bisa dijangkau
 *   - Dev/admin login lokal
 *   - Testing dari luar jaringan RS
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloak
    ) {}

    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('icu.dashboard');
        }

        return Inertia::render('Auth/Login', [
            'flash'              => ['error' => session('error')],
            // Flag untuk Vue: tampilkan tombol SSO jika Keycloak bisa dijangkau
            'keycloakAvailable'  => $this->keycloak->isReachable(),
            'keycloakRedirectUrl'=> route('auth.keycloak'),
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

        // Blok user Keycloak dari login lokal — mereka harus pakai SSO
        if ($user->isKeycloakUser()) {
            return back()->with('error', 'Akun ini menggunakan SSO. Silakan login dengan tombol SSO.');
        }

        if (Auth::attempt(
            ['username' => $request->username, 'password' => $request->password],
            $request->boolean('remember')
        )) {
            $request->session()->regenerate();
            $request->session()->put('auth_via', 'local');
            return redirect()->intended(route('icu.dashboard'));
        }

        return back()->with('error', 'Username atau password salah.');
    }

    public function logout(Request $request): RedirectResponse
    {
        // Delegasi ke AuthController untuk handle Keycloak logout jika perlu
        $authController = app(AuthController::class);
        return $authController->logout($request);
    }
}
