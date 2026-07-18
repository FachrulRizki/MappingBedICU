<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeycloakService
{
    private string $baseUrl;
    private string $realm;
    private string $clientId;
    private string $clientSecret;
    private int    $timeout;
    private int    $cacheTtl;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('keycloak.base_url', ''), '/');
        $this->realm        = config('keycloak.realm', 'myrealm');
        $this->clientId     = config('keycloak.client_id', '');
        $this->clientSecret = config('keycloak.client_secret', '');
        $this->timeout      = (int) config('keycloak.timeout', 5);
        $this->cacheTtl     = (int) config('keycloak.cache_ttl', 60);
    }

    // OIDC Endpoints ────────────────────────────────────────────────────────

    private function oidcBase(): string      { return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect"; }
    private function tokenUrl(): string      { return $this->oidcBase() . '/token'; }
    private function introspectUrl(): string { return $this->oidcBase() . '/token/introspect'; }
    private function logoutUrl(): string     { return $this->oidcBase() . '/logout'; }

    // Reachability ──────────────────────────────────────────────────────────

    /** Cek apakah Keycloak bisa dijangkau — untuk show/hide tombol SSO. */
    public function isReachable(): bool
    {
        if (! config('keycloak.enabled', true)) {
            return false;
        }

        try {
            return Http::timeout(3)
                ->get("{$this->baseUrl}/realms/{$this->realm}/.well-known/openid-configuration")
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    // Token

    /** Introspect token — hasil di-cache untuk kurangi round-trip ke Keycloak. */
    public function introspectToken(string $accessToken): array
    {
        $key = 'keycloak_token:' . hash('sha256', $accessToken);

        return Cache::remember($key, $this->cacheTtl, fn () =>
            Http::timeout($this->timeout)
                ->asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post($this->introspectUrl(), ['token' => $accessToken])
                ->json() ?? []
        );
    }

    /** Refresh access token. */
    public function refreshToken(string $refreshToken): array
    {
        return Http::timeout($this->timeout)
            ->asForm()
            ->post($this->tokenUrl(), [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
            ])
            ->json() ?? [];
    }

    /** Hapus cache introspection — dipanggil saat logout. */
    public function forgetTokenCache(string $accessToken): void
    {
        Cache::forget('keycloak_token:' . hash('sha256', $accessToken));
    }

    // JWT

    /** Decode payload JWT tanpa verifikasi signature (claims dibaca saja). */
    public function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        return count($parts) === 3
            ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? []
            : [];
    }

    // Role
    public function getRolesFromToken(array $payload): array
    {
        $realm  = $payload['realm_access']['roles'] ?? [];
        $client = $payload['resource_access'][$this->clientId]['roles'] ?? [];

        $systemRoles = [
            'offline_access',
            'uma_authorization',
            'default-roles-' . $this->realm,
        ];

        $all = array_values(array_unique(array_merge($realm, $client)));

        // Filter system roles dan client roles berbentuk permission (mengandung ":")
        return array_values(array_filter(
            $all,
            fn ($r) => ! in_array($r, $systemRoles, true) && ! str_contains($r, ':')
        ));
    }

    public function resolveRoleFromToken(array $payload): string
    {
        $roles = $this->getRolesFromToken($payload);

        return $roles[0] ?? 'user';
    }

    // Permission
    public function extractPermissionsFromToken(array $payload): array
    {
        $perms = $this->parseAuthorizationPermissions($payload);

        if (! empty($perms)) {
            Log::debug('[Keycloak] Permissions (Authorization Services): ' . implode(', ', $perms));
            return $perms;
        }

        $clientRoles = $payload['resource_access'][$this->clientId]['roles'] ?? [];
        Log::debug('[Keycloak] Client roles raw [' . $this->clientId . ']: ' . implode(', ', $clientRoles) ?: '(kosong)');
        Log::debug('[Keycloak] resource_access keys: ' . implode(', ', array_keys($payload['resource_access'] ?? [])));

        $perms = array_values(array_unique(
            array_filter($clientRoles, fn ($r) => str_contains($r, ':'))
        ));

        if (! empty($perms)) {
            Log::debug('[Keycloak] Permissions (client roles): ' . implode(', ', $perms));
            return $perms;
        }

        Log::debug('[Keycloak] Tidak ada permissions ditemukan di token.');
        return [];
    }

    /**
     * Validasi apakah token layak login ke aplikasi ini.
     */
    public function hasAppAccess(array $payload): bool
    {
        return ! empty($this->extractPermissionsFromToken($payload));
    }

    /** Cek apakah user punya salah satu dari permissions yang diminta (OR). */
    public function hasAnyPermission(array $userPermissions, array $required): bool
    {
        return (bool) array_intersect($userPermissions, $required);
    }

    // Logout

    /** Bangun URL logout Keycloak — sertakan id_token_hint jika ada. */
    public function buildLogoutUrl(string $postRedirect, ?string $idToken = null): string
    {
        $params = [
            'client_id'                => $this->clientId,
            'post_logout_redirect_uri' => $postRedirect,
        ];

        if ($idToken) {
            $params['id_token_hint'] = $idToken;
        }

        return $this->logoutUrl() . '?' . http_build_query($params);
    }

    // Private

    private function parseAuthorizationPermissions(array $payload): array
    {
        // Key: Resource Set Name di Keycloak → prefix permission di aplikasi
        $map = [
            'booking-external' => 'booking_ext',
            'booking-internal' => 'booking_int',
            'dashboard'        => 'dashboard',
            'denah-bed'        => 'denah_bed',
            'activity-log'     => 'activity_log',
            // User & role dikelola Keycloak — tidak ada resource settings-users/roles
        ];

        $perms = [];

        foreach ($payload['authorization']['permissions'] ?? [] as $item) {
            $resource = $item['Resource Set Name'] ?? null;
            if (! $resource) continue;

            $prefix = $map[$resource] ?? str_replace('-', '_', $resource);

            foreach ($item['scopes'] ?? [] as $scope) {
                $perms[] = "{$prefix}:{$scope}";
            }
        }

        return array_values(array_unique($perms));
    }
}
