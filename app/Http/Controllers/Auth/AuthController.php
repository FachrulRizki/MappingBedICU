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

    public function handleCallback(Request $request): RedirectResponse
    {
        // Keycloak mengirim error saat user cancel login
        if ($request->has('error')) {
            $desc = $request->query('error_description', 'Login dibatalkan.');
            Log::warning('[Keycloak Callback] Error: ' . $desc);
            return redirect()->route('login')->with('error', 'Login SSO gagal: ' . $desc);
        }

        try {
            // Exchange code -> user object via Socialite
            $socialUser = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            Log::error('[Keycloak Callback] ' . $e->getMessage());
            return redirect()->route('login')
                ->with('error', 'Gagal menghubungi server SSO. Coba lagi atau gunakan login lokal.');
        }

        // sso
        $tokenPayload = $this->keycloak->decodeJwtPayload($socialUser->token);
        $realmRoles   = $tokenPayload['realm_access']['roles'] ?? [];
        // lokal
        $localRole    = $this->keycloak->resolveRoleFromToken($tokenPayload);

        // Ambil id_token dari response body (WAJIB untuk logout SSO yang benar)
        $idToken = $socialUser->accessTokenResponseBody['id_token'] ?? null;
        if (! $idToken) {
            Log::warning('[Keycloak] id_token tidak ditemukan di response — logout SSO mungkin tidak bekerja.');
        }

        // Upsert user lokal
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

        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        // Simpan id_token asli untuk keperluan logout SSO
        $request->session()->put('auth_via', 'keycloak');
        $request->session()->put('keycloak_id_token', $idToken);
        // Simpan raw realm roles untuk keperluan re-sync tanpa login ulang
        $request->session()->put('keycloak_realm_roles', $realmRoles);
        // Simpan seluruh token payload agar SyncKeycloakRole bisa cek client roles juga
        $request->session()->put('keycloak_token_payload', $tokenPayload);

        Log::info("[Keycloak] Login: {$user->name} role:{$localRole} id_token:" . ($idToken ? 'ada' : 'KOSONG'));

        $this->activityLog->loginLog();

        return redirect()->intended(route('icu.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $authVia = $request->session()->get('auth_via', 'local');
        $idToken = $request->session()->get('keycloak_id_token');
        $userId  = Auth::id();

        // Catat logout sebelum session di-invalidate
        $this->activityLog->logoutLog();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // cek kondisi db lokal dan sso
        if ($userId) {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $userId)
                ->delete();
        }

        if ($authVia === 'keycloak') {
            $baseUrl  = rtrim(config('services.keycloak.base_url', ''), '/');
            $realm    = config('services.keycloak.realms', 'myrealm');
            $clientId = config('services.keycloak.client_id');

            // post_logout_redirect_uri harus sudah didaftarkan di Keycloak client
            $postLogout = urlencode(route('login'));

            $logoutUrl = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout"
                . "?client_id=" . urlencode($clientId)
                . "&post_logout_redirect_uri={$postLogout}";

            // id_token_hint adalah kunci utama agar Keycloak invalidate SSO session
            if ($idToken) {
                $logoutUrl .= "&id_token_hint=" . urlencode($idToken);
                Log::info("[Keycloak] Logout dengan id_token_hint untuk user_id:{$userId}");
            } else {
                Log::warning("[Keycloak] Logout tanpa id_token_hint — user_id:{$userId}. Pakai fallback.");
            }

            return redirect($logoutUrl);
        }

        return redirect()->route('login');
    }
}
