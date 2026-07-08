<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KeycloakService
{
    public function isReachable(): bool
    {
        $enabled = config('services.keycloak.enabled', 'auto');

        if ($enabled === 'false' || $enabled === false) {
            return false;
        }

        if ($enabled === 'true' || $enabled === true) {
            return true;
        };
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

    /**
     * Cek apakah token mengandung minimal satu role yang dikenali aplikasi.
     * Digunakan untuk menolak login user yang belum di-setup role-nya di Keycloak.
     */
    public function hasRecognizedRole(array $tokenPayload): bool
    {
        $knownRoles  = ['admin', 'admisi', 'petugas_icu', 'petugas_ruang'];
        $clientRoles = $this->extractClientRoles($tokenPayload);
        $realmRoles  = $tokenPayload['realm_access']['roles'] ?? [];

        foreach (array_merge($clientRoles, $realmRoles) as $role) {
            if (in_array($role, $knownRoles, true)) {
                return true;
            }
        }

        return false;
    }

    public function extractPermissionsFromToken(array $tokenPayload): array
    {
        return app(\App\Services\KeycloakPermissionService::class)
            ->extractPermissionsFromToken($tokenPayload);
    }

    // Introspect access token ke Keycloak untuk verifikasi aktif/tidak.
    public function introspectToken(string $accessToken): array
    {
        $baseUrl  = rtrim(config('services.keycloak.base_url', ''), '/');
        $realm    = config('services.keycloak.realms', 'myrealm');
        $clientId = config('services.keycloak.client_id');
        $secret   = config('services.keycloak.client_secret');

        if (! $baseUrl || ! $clientId || ! $secret) {
            Log::debug('[Keycloak] Introspect skip — config tidak lengkap.');
            return [];
        }

        $url = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/token/introspect";

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'token'        => $accessToken,
                    'client_id'    => $clientId,
                    'client_secret'=> $secret,
                ]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                Log::warning("[Keycloak] Introspect gagal (HTTP {$httpCode}): {$error}");
                return [];
            }

            return json_decode($response, true) ?? [];
        } catch (\Throwable $e) {
            Log::warning('[Keycloak] Introspect exception: ' . $e->getMessage());
            return [];
        }
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