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

    // Ward
    public function getWardIdsArray(): array
    {
        return $this->ward_ids ?? [];
    }

    // Auth provider
    public function isKeycloakUser(): bool
    {
        return $this->auth_provider === 'keycloak';
    }

    public function isLocalUser(): bool
    {
        return $this->auth_provider === 'local' || $this->auth_provider === null;
    }

    // Role label & color — dinamis, tidak hardcode
    public function roleLabel(): string
    {
        if (! $this->role) return 'User';

        return ucwords(str_replace(['_', '-'], ' ', $this->role));
    }

    /**
     * Warna badge untuk role — diambil dari palet tetap berdasarkan hash nama role.
     * Role yang sama selalu dapat warna yang sama, role baru otomatis dapat warna.
     */
    public function roleColor(): string
    {
        $palette = [
            '#E0923A', '#4A90D9', '#2DD9A4', '#D9517A',
            '#9B59B6', '#1ABC9C', '#E74C3C', '#3498DB',
            '#F39C12', '#27AE60', '#8E44AD', '#16A085',
        ];

        $index = abs(crc32($this->role ?? '')) % count($palette);

        return $palette[$index];
    }
}
