<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\KeycloakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * AuthController — menangani SSO Keycloak.
 *
 * Dual-mode login:
 *   - Jaringan RS (Keycloak reachable) → SSO via Keycloak
 *   - Lokal / offline                  → form username + password (LoginController)
 *
 * Alur SSO:
 *   /auth/keycloak          → redirect ke Keycloak login page
 *   /auth/keycloak/callback → terima code, exchange token, upsert user, Auth::login
 *   /auth/keycloak/logout   → revoke session + redirect ke Keycloak logout
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloak
    ) {}

    /**
     * Redirect user ke halaman login Keycloak.
     */
    public function redirectToKeycloak(): RedirectResponse
    {
        // Pastikan Keycloak masih bisa dijangkau sebelum redirect
        if (! $this->keycloak->isReachable()) {
            return redirect()->route('login')
                ->with('error', 'Server SSO tidak dapat dijangkau. Gunakan login lokal.');
        }

        return Socialite::driver('keycloak')->redirect();
    }

    /**
     * Handle callback dari Keycloak setelah user login.
     *
     * 1. Exchange authorization code → access token (Socialite)
     * 2. Decode JWT payload → ambil realm_access.roles
     * 3. Map role Keycloak → role lokal
     * 4. Upsert user lokal berdasarkan keycloak_id
     * 5. Auth::login → redirect ke dashboard
     */
    public function handleCallback(Request $request): RedirectResponse
    {
        // Keycloak mengirim error saat user cancel login
        if ($request->has('error')) {
            $desc = $request->query('error_description', 'Login dibatalkan.');
            Log::warning('[Keycloak Callback] Error: ' . $desc);
            return redirect()->route('login')->with('error', 'Login SSO gagal: ' . $desc);
        }

        try {
            // Exchange code → user object via Socialite
            $socialUser = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            Log::error('[Keycloak Callback] ' . $e->getMessage());
            return redirect()->route('login')
                ->with('error', 'Gagal menghubungi server SSO. Coba lagi atau gunakan login lokal.');
        }

        // Decode JWT untuk ambil realm_access.roles
        $tokenPayload  = $this->keycloak->decodeJwtPayload($socialUser->token);
        $realmRoles    = $tokenPayload['realm_access']['roles'] ?? [];
        $localRole     = $this->keycloak->mapRole($realmRoles);

        // Upsert user lokal — cari by keycloak_id, create/update jika perlu
        $user = User::updateOrCreate(
            ['keycloak_id' => $socialUser->getId()],
            [
                'name'               => $socialUser->getName()     ?: $socialUser->getNickname(),
                'email'              => $socialUser->getEmail()     ?: null,
                'keycloak_username'  => $socialUser->getNickname() ?: null,
                'username'           => $socialUser->getNickname() ?: 'kc_' . $socialUser->getId(),
                'role'               => $localRole,
                'auth_provider'      => 'keycloak',
                'is_active'          => true,
                'password'           => null,  // SSO user tidak punya password lokal
            ]
        );

        // Login ke session Laravel
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Tandai bahwa session ini via Keycloak (untuk logout yang benar)
        $request->session()->put('auth_via', 'keycloak');
        $request->session()->put('keycloak_id_token', $tokenPayload['sid'] ?? null);

        Log::info("[Keycloak] User login: {$user->name} ({$user->email}) role:{$localRole}");

        return redirect()->intended(route('icu.dashboard'));
    }

    /**
     * Logout — dari session Laravel + revoke SSO session di Keycloak.
     *
     * Jika login via Keycloak: redirect ke Keycloak logout endpoint
     * agar session SSO di semua aplikasi di realm ikut di-terminate.
     *
     * Jika login lokal: langsung redirect ke /login.
     */
    public function logout(Request $request): RedirectResponse
    {
        $authVia = $request->session()->get('auth_via', 'local');

        // Hapus session Laravel
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($authVia === 'keycloak') {
            $baseUrl    = rtrim(config('services.keycloak.base_url', ''), '/');
            $realm      = config('services.keycloak.realms', 'myrealm');
            $clientId   = config('services.keycloak.client_id');
            $postLogout = urlencode(route('login'));

            // Keycloak logout endpoint — session SSO di-terminate
            $logoutUrl = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout"
                . "?post_logout_redirect_uri={$postLogout}"
                . "&client_id={$clientId}";

            return redirect($logoutUrl);
        }

        return redirect()->route('login');
    }
}
