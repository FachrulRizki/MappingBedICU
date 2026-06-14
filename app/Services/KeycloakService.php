<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * KeycloakService — utilitas untuk SSO Keycloak.
 *
 * Menangani:
 *   1. Cek apakah Keycloak bisa dijangkau (untuk dual-mode login)
 *   2. Mapping Realm Roles Keycloak → role lokal aplikasi
 *   3. Decode JWT payload tanpa verifikasi signature
 *      (hanya untuk baca claims — Socialite sudah verifikasi token-nya)
 */
class KeycloakService
{
    /**
     * Cek apakah Keycloak server bisa dijangkau.
     *
     * Hasil di-cache 30 detik agar tidak ada request HTTP di setiap page load.
     * Endpoint yang dicek: /.well-known/openid-configuration (ringan, tidak butuh auth).
     */
    public function isReachable(): bool
    {
        // Jika KEYCLOAK_ENABLED=false di .env, nonaktifkan SSO tanpa cek jaringan
        if (env('KEYCLOAK_ENABLED', 'auto') === 'false') {
            return false;
        }

        // Jika KEYCLOAK_ENABLED=true, paksa aktif (untuk testing di jaringan RS)
        if (env('KEYCLOAK_ENABLED', 'auto') === 'true') {
            return true;
        }

        // Mode auto (default): cek koneksi aktual ke Keycloak server
        return Cache::remember('keycloak_reachable', 30, function () {
            return $this->pingKeycloak();
        });
    }

    /**
     * Ping endpoint openid-configuration Keycloak.
     * Timeout 2 detik — tidak akan block UI jika server tidak bisa dijangkau.
     */
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
                CURLOPT_NOBODY         => true,     // HEAD request — tidak download body
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

    /**
     * Mapping Realm Roles dari Keycloak → role lokal aplikasi.
     *
     * Keycloak realm roles ada di JWT claim: realm_access.roles[]
     * Prioritas: jika user punya lebih dari satu role, ambil yang paling tinggi.
     *
     * Nama role di Keycloak bisa disesuaikan di sini tanpa ubah kode lain.
     *
     * @param  array  $realmRoles  — dari $tokenPayload['realm_access']['roles']
     * @return string              — role lokal ('admin'|'admisi'|'petugas_icu'|'petugas_ruang')
     */
    public function mapRole(array $realmRoles): string
    {
        // Mapping: nama role di Keycloak → role lokal
        // Sesuaikan kunci kiri dengan nama role yang ada di Keycloak realm
        $map = [
            // Role admin
            'admin'               => 'admin',
            'icu-admin'           => 'admin',

            // Role admisi
            'admisi'              => 'admisi',
            'icu-admisi'          => 'admisi',
            'petugas-admisi'      => 'admisi',

            // Role petugas ICU
            'petugas_icu'         => 'petugas_icu',
            'icu-petugas'         => 'petugas_icu',
            'petugas-icu'         => 'petugas_icu',
            'nurse-icu'           => 'petugas_icu',

            // Role petugas ruang
            'petugas_ruang'       => 'petugas_ruang',
            'petugas-ruang'       => 'petugas_ruang',
            'nurse'               => 'petugas_ruang',
        ];

        // Urutan prioritas (role tertinggi diambil lebih dulu)
        $priority = ['admin', 'petugas_icu', 'admisi', 'petugas_ruang'];

        // Kumpulkan semua local role yang cocok
        $matched = [];
        foreach ($realmRoles as $keycloakRole) {
            if (isset($map[$keycloakRole])) {
                $matched[] = $map[$keycloakRole];
            }
        }

        // Kembalikan role dengan prioritas tertinggi
        foreach ($priority as $r) {
            if (in_array($r, $matched)) {
                return $r;
            }
        }

        // Fallback jika tidak ada role yang cocok
        Log::warning('[Keycloak] Role tidak dikenali: ' . implode(', ', $realmRoles) . ' — fallback ke petugas_ruang');
        return 'petugas_ruang';
    }

    /**
     * Decode JWT payload tanpa verifikasi signature.
     *
     * Aman digunakan di sini karena token sudah divalidasi oleh Socialite
     * saat exchange authorization code → access token.
     * Kita hanya butuh baca claims (realm_access, preferred_username, dll).
     *
     * @param  string  $token  — raw JWT access token dari Socialite
     * @return array           — decoded payload, atau [] jika gagal
     */
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
