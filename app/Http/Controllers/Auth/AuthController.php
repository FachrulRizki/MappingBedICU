<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\KeycloakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private readonly KeycloakService    $keycloak,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Redirect user ke halaman login Keycloak.
     */
    public function redirectToKeycloak(): RedirectResponse
    {
        if (! $this->keycloak->isReachable()) {
            return redirect()->route('login')
                ->with('error', 'Server SSO tidak dapat dijangkau. Gunakan login lokal.');
        }

        /** @var \SocialiteProviders\Keycloak\Provider $provider */
        $provider = Socialite::driver('keycloak');
        return $provider
            ->with(['prompt' => 'login'])
            ->redirect();
    }

    /**
     * Handle callback dari Keycloak setelah user login SSO.
     */
    public function handleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $desc = $request->query('error_description', 'Login dibatalkan.');
            Log::warning('[Keycloak Callback] Error: ' . $desc);
            return redirect()->route('login')->with('error', 'Login SSO gagal: ' . $desc);
        }

        try {
            $socialUser = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            Log::error('[Keycloak Callback] ' . $e->getMessage());
            return redirect()->route('login')
                ->with('error', 'Gagal menghubungi server SSO. Coba lagi atau gunakan login lokal.');
        }

        $tokenPayload = $this->keycloak->decodeJwtPayload($socialUser->token);
        $realmRoles   = $tokenPayload['realm_access']['roles'] ?? [];
        $localRole    = $this->keycloak->resolveRoleFromToken($tokenPayload);
        $idToken      = $socialUser->accessTokenResponseBody['id_token'] ?? null;

        if (! $idToken) {
            Log::warning('[Keycloak] id_token tidak ditemukan — logout SSO mungkin tidak bekerja.');
        }

        // Tolak login jika user belum di-assign role yang dikenali di Keycloak
        if (! $this->keycloak->hasRecognizedRole($tokenPayload)) {
            Log::warning('[Keycloak] Login ditolak — tidak punya role yang dikenali: '
                . $socialUser->getNickname() . ' roles: ' . implode(', ', $realmRoles));
            return redirect()->route('login')
                ->with('error', 'Akun Anda belum memiliki role yang sesuai. Hubungi administrator.');
        }

        // Upsert user — buat baru kalau belum ada, update kalau sudah ada
        $user = User::updateOrCreate(
            ['keycloak_id' => $socialUser->getId()],
            [
                'name'              => $socialUser->getName()     ?: $socialUser->getNickname(),
                'email'             => $socialUser->getEmail()    ?: null,
                'keycloak_username' => $socialUser->getNickname() ?: null,
                'username'          => $socialUser->getNickname() ?: 'kc_' . $socialUser->getId(),
                'role'              => $localRole,
                'auth_provider'     => 'keycloak',
                'is_active'         => true,
                'password'          => null,
                'ward_ids'          => $tokenPayload['ward_ids'] ?? null,
            ]
        );

        Auth::login($user, remember: false);
        $request->session()->regenerate();
        $request->session()->put('auth_via', 'keycloak');
        $request->session()->put('keycloak_id_token', $idToken);
        $request->session()->put('keycloak_token_payload', $tokenPayload);

        Log::info("[Keycloak] Login: {$user->name} role:{$localRole} id_token:" . ($idToken ? 'ada' : 'KOSONG'));

        try {
            $this->activityLog->loginLog();
        } catch (\Throwable $e) {
            Log::warning('[Keycloak] Activity log login gagal: ' . $e->getMessage());
        }

        return redirect()->intended(route('icu.dashboard'));
    }

    /**
     * Logout dari aplikasi dan invalidate SSO session di Keycloak.
     */
    public function logout(Request $request): RedirectResponse
    {
        $authVia = $request->session()->get('auth_via', 'local');
        $idToken = $request->session()->get('keycloak_id_token');
        $userId  = Auth::id();

        try {
            $this->activityLog->logoutLog();
        } catch (\Throwable $e) {
            Log::warning('[Keycloak] Activity log logout gagal: ' . $e->getMessage());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($authVia === 'keycloak') {
            $baseUrl  = rtrim(config('services.keycloak.base_url', ''), '/');
            $realm    = config('services.keycloak.realms', 'myrealm');
            $clientId = config('services.keycloak.client_id');

            $logoutUrl = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout"
                . "?client_id=" . urlencode($clientId)
                . "&post_logout_redirect_uri=" . urlencode(route('login'));

            if ($idToken) {
                $logoutUrl .= "&id_token_hint=" . urlencode($idToken);
                Log::info("[Keycloak] Logout dengan id_token_hint untuk user_id:{$userId}");
            } else {
                Log::warning("[Keycloak] Logout tanpa id_token_hint — user_id:{$userId}.");
            }

            return redirect($logoutUrl);
        }

        return redirect()->route('login');
    }
}
