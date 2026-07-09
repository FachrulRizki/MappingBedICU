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

    // ── OIDC Endpoints ────────────────────────────────────────────────────────

    private function oidcBase(): string      { return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect"; }
    private function tokenUrl(): string      { return $this->oidcBase() . '/token'; }
    private function introspectUrl(): string { return $this->oidcBase() . '/token/introspect'; }
    private function logoutUrl(): string     { return $this->oidcBase() . '/logout'; }

    // ── Reachability ──────────────────────────────────────────────────────────

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

    // ── Token ─────────────────────────────────────────────────────────────────

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

    // ── JWT ───────────────────────────────────────────────────────────────────

    /** Decode payload JWT tanpa verifikasi signature (claims dibaca saja). */
    public function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        return count($parts) === 3
            ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? []
            : [];
    }

    // ── Role ──────────────────────────────────────────────────────────────────

    /** Semua role yang dikenali aplikasi (urutan = prioritas resolve). */
    private function recognizedRoles(): array
    {
        return ['admin', 'admisi', 'petugas_icu', 'petugas_ruang'];
    }

    /** Gabungkan realm roles + client roles dari token payload. */
    public function getRolesFromToken(array $payload): array
    {
        $realm  = $payload['realm_access']['roles'] ?? [];
        $client = $payload['resource_access'][$this->clientId]['roles'] ?? [];

        return array_values(array_unique(array_merge($realm, $client)));
    }

    /** Cek apakah token punya minimal satu role yang dikenali. */
    public function hasRecognizedRole(array $payload): bool
    {
        $roles = $this->getRolesFromToken($payload);

        return (bool) array_intersect($this->recognizedRoles(), $roles);
    }

    /** Pilih satu role lokal sesuai prioritas — fallback ke petugas_ruang. */
    public function resolveRoleFromToken(array $payload): string
    {
        $roles = $this->getRolesFromToken($payload);

        foreach ($this->recognizedRoles() as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'petugas_ruang';
    }

    // ── Permission ────────────────────────────────────────────────────────────

    /**
     * Ekstrak permissions dari token.
     * Prioritas: Keycloak Authorization Services → client roles berbentuk "resource:scope".
     */
    public function extractPermissionsFromToken(array $payload): array
    {
        // 1 — Authorization Services
        $perms = $this->parseAuthorizationPermissions($payload);

        if (! empty($perms)) {
            Log::debug('[Keycloak] Permissions (Authorization Services): ' . implode(', ', $perms));
            return $perms;
        }

        // 2 — Client roles sebagai permissions (format "resource:scope")
        $clientRoles = $payload['resource_access'][$this->clientId]['roles'] ?? [];
        $perms       = array_values(array_unique(
            array_filter($clientRoles, fn ($r) => str_contains($r, ':'))
        ));

        if (! empty($perms)) {
            Log::debug('[Keycloak] Permissions (client roles): ' . implode(', ', $perms));
            return $perms;
        }

        return [];
    }

    /** Cek apakah user punya salah satu dari permissions yang diminta (OR). */
    public function hasAnyPermission(array $userPermissions, array $required): bool
    {
        return (bool) array_intersect($userPermissions, $required);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

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

    // ── Private ───────────────────────────────────────────────────────────────

    private function parseAuthorizationPermissions(array $payload): array
    {
        $map = [
            'booking-external' => 'booking_ext',
            'booking-internal' => 'booking_int',
            'dashboard'        => 'dashboard',
            'denah-bed'        => 'denah_bed',
            'settings-users'   => 'settings_users',
            'settings-roles'   => 'settings_roles',
            'activity-log'     => 'activity_log',
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
