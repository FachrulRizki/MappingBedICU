<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KeycloakService
{
    public function isReachable(): bool
    {
        // Jika KEYCLOAK_ENABLED=false di .env, nonaktifkan SSO tanpa cek jaringan
        if (env('KEYCLOAK_ENABLED', 'auto') === 'false') {
            return false;
        }

        // Jika KEYCLOAK_ENABLED=true, aktif (untuk testing di jaringan RS)
        if (env('KEYCLOAK_ENABLED', 'auto') === 'true') {
            return true;
        }

        // Mode auto (default): cek koneksi aktual ke Keycloak server
        return Cache::remember('keycloak_reachable', 30, function () {
            return $this->pingKeycloak();
        });
    }

    private function pingKeycloak(): bool
    {
        $baseUrl = rtrim(config('services.keycloak.base_url', ''), '/');
        $realm   = config('services.keycloak.realms', 'myrealm');
        $url     = "{$baseUrl}/realms/{$realm}/.well-known/openid-configuration";

        if (! $baseUrl) {
            return false;
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 2,
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::debug("[Keycloak] Ping gagal: {$error}");
                return false;
            }

            return $httpCode >= 200 && $httpCode < 500;
        } catch (\Throwable $e) {
            Log::debug("[Keycloak] Ping exception: " . $e->getMessage());
            return false;
        }
    }

    public function mapRole(array $realmRoles): string
    {
        $map = [
            // Role admin
            'admin'               => 'admin',

            // Role admisi
            'admisi'              => 'admisi',

            // Role petugas ICU
            'petugas_icu'         => 'petugas_icu',

            // Role petugas ruang
            'petugas_ruang'       => 'petugas_ruang',
        ];

        $priority = ['admin', 'petugas_icu', 'admisi', 'petugas_ruang'];

        $matched = [];
        foreach ($realmRoles as $keycloakRole) {
            if (isset($map[$keycloakRole])) {
                $matched[] = $map[$keycloakRole];
            }
        }

        foreach ($priority as $r) {
            if (in_array($r, $matched)) {
                return $r;
            }
        }

        Log::warning('[Keycloak] Role tidak dikenali: ' . implode(', ', $realmRoles) . ' — fallback ke petugas_ruang');
        return 'petugas_ruang';
    }

    public function extractClientRoles(array $tokenPayload): array
    {
        $clientId = config('services.keycloak.client_id', 'icu-bed');
        return $tokenPayload['resource_access'][$clientId]['roles'] ?? [];
    }

    public function resolveRoleFromToken(array $tokenPayload): string
    {
        $clientRoles = $this->extractClientRoles($tokenPayload);
        if (!empty($clientRoles)) {
            return $this->mapRole($clientRoles);
        }

        $realmRoles = $tokenPayload['realm_access']['roles'] ?? [];
        return $this->mapRole($realmRoles);
    }

    public function extractPermissionsFromToken(array $tokenPayload): array
    {
        return app(\App\Services\KeycloakPermissionService::class)
            ->extractPermissionsFromToken($tokenPayload);
    }

    public function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }

        try {
            $payload = base64_decode(str_pad(
                strtr($parts[1], '-_', '+/'),
                strlen($parts[1]) % 4 === 0 ? strlen($parts[1]) : strlen($parts[1]) + (4 - strlen($parts[1]) % 4),
                '='
            ));
            return json_decode($payload, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}