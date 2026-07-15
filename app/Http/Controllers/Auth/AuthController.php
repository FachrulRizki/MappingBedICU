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

    /** Redirect ke halaman login Keycloak via Socialite. */
    public function redirectToKeycloak(): RedirectResponse
    {
        if (! $this->keycloak->isReachable()) {
            return redirect()->route('login')
                ->with('error', 'Server SSO tidak dapat dijangkau.');
        }

        return Socialite::driver('keycloak')->with(['prompt' => 'login'])->redirect();
    }

    /** Proses callback dari Keycloak — validasi token, upsert user, login. */
    public function handleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $desc = $request->query('error_description', 'Login dibatalkan.');
            Log::warning("[Keycloak] Callback error: {$desc}");
            return redirect()->route('login')->with('error', "Login SSO gagal: {$desc}");
        }

        try {
            $socialUser = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            Log::error('[Keycloak] ' . $e->getMessage());
            return redirect()->route('login')
                ->with('error', 'Gagal menghubungi server SSO. Coba lagi atau gunakan login lokal.');
        }

        $payload = $this->keycloak->decodeJwtPayload($socialUser->token);
        $idToken = $socialUser->accessTokenResponseBody['id_token'] ?? null;

        // Tolak jika tidak punya permission apapun di app ini
        if (! $this->keycloak->hasAppAccess($payload)) {
            Log::warning('[Keycloak] Login ditolak — tidak ada permission: '
                . $socialUser->getNickname()
                . ' roles: [' . implode(', ', $this->keycloak->getRolesFromToken($payload)) . ']');
            return redirect()->route('login')
                ->with('error', 'Akun Anda belum memiliki akses ke aplikasi ini. Hubungi administrator.');
        }

        $keycloakId = $socialUser->getId();
        $email      = $socialUser->getEmail() ?: null;

        // Cari user by keycloak_id dulu, fallback ke email
        $user = User::where('keycloak_id', $keycloakId)->first()
            ?? ($email ? User::where('email', $email)->whereNull('keycloak_id')->first() : null);

        $attributes = [
            'name'              => $socialUser->getName() ?: $socialUser->getNickname(),
            'email'             => $email,
            'username'          => $socialUser->getNickname() ?: 'kc_' . $keycloakId,
            'keycloak_id'       => $keycloakId,
            'keycloak_username' => $socialUser->getNickname() ?: null,
            'role'              => $this->keycloak->resolveRoleFromToken($payload),
            'auth_provider'     => 'keycloak',
            'is_active'         => true,
            'password'          => null,
            'ward_ids'          => $payload['ward_ids'] ?? null,
        ];

        if ($user) {
            $user->update($attributes);
            $user->refresh();
        } else {
            $user = User::create($attributes);
        }

        Auth::login($user, remember: false);

        $request->session()->regenerate();
        $request->session()->put([
            'auth_via'                 => 'keycloak',
            'keycloak_id_token'        => $idToken,
            'keycloak_token_payload'   => $payload,
            'keycloak_access_token'    => $socialUser->token,
            'keycloak_refresh_token'   => $socialUser->refreshToken ?? null,
            'keycloak_last_introspect' => time(),
            'keycloak_permissions'     => $this->keycloak->extractPermissionsFromToken($payload),
        ]);
        $request->session()->save();

        Log::info("[Keycloak] Login: {$user->name} role:{$user->role}");

        try { $this->activityLog->loginLog(); } catch (\Throwable) {}

        return redirect()->intended(route('icu.dashboard'));
    }

    /** Logout lokal + invalidate sesi Keycloak. */
    public function logout(Request $request): RedirectResponse
    {
        $authVia     = $request->session()->get('auth_via', 'local');
        $idToken     = $request->session()->get('keycloak_id_token');
        $accessToken = $request->session()->get('keycloak_access_token');

        try { $this->activityLog->logoutLog(); } catch (\Throwable) {}

        if ($accessToken) {
            $this->keycloak->forgetTokenCache($accessToken);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->save();

        if ($authVia === 'keycloak') {
            return redirect($this->keycloak->buildLogoutUrl(route('login'), $idToken));
        }

        return redirect()->route('login');
    }
}
