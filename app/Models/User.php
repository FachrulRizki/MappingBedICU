<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'IB_users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'unit_kerja',
        'is_active',
        // SSO Keycloak
        'keycloak_id',
        'keycloak_username',
        'auth_provider',
        'ward_ids',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'ward_ids'          => 'array',
        ];
    }

    /**
     * Kode bangsal yang menjadi scope akses user ini.
     * Dari Keycloak token field 'ward_ids'.
     */
    public function getWardIdsArray(): array
    {
        return $this->ward_ids ?? [];
    }

    // ── Auth provider helpers ──────────────────────────────────────────────

    public function isKeycloakUser(): bool
    {
        return $this->auth_provider === 'keycloak';
    }

    public function isLocalUser(): bool
    {
        return $this->auth_provider === 'local' || $this->auth_provider === null;
    }

    // ── Role helpers ───────────────────────────────────────────────────────

    public function isAdmin(): bool        { return $this->role === 'admin'; }
    public function isAdmisi(): bool       { return $this->role === 'admisi'; }
    public function isIcu(): bool          { return $this->role === 'petugas_icu'; }
    public function isPetugasRuang(): bool { return $this->role === 'petugas_ruang'; }

    public function canManageAdmisi(): bool
    {
        return in_array($this->role, ['admin', 'admisi']);
    }

    public function canManageIcu(): bool
    {
        return in_array($this->role, ['admin', 'petugas_icu']);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin'         => 'Administrator',
            'admisi'        => 'Petugas Admisi',
            'petugas_icu'   => 'Petugas ICU',
            'petugas_ruang' => 'Petugas Ruang',
            default         => $this->role,
        };
    }

    public function roleColor(): string
    {
        return match ($this->role) {
            'admin'         => '#E0923A',
            'admisi'        => '#4A90D9',
            'petugas_icu'   => '#2DD9A4',
            'petugas_ruang' => '#D9517A',
            default         => '#8EA89E',
        };
    }
}
