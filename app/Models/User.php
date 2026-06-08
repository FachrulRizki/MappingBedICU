<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'unit_kerja',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ── Role helpers ──────────────────────────────────────────

    public function isAdmin(): bool       { return $this->role === 'admin'; }
    public function isAdmisi(): bool      { return $this->role === 'admisi'; }
    public function isIcu(): bool         { return $this->role === 'petugas_icu'; }
    public function isPetugasRuang(): bool{ return $this->role === 'petugas_ruang'; }

    /** Admin atau admisi bisa kelola booking/surat */
    public function canManageAdmisi(): bool
    {
        return in_array($this->role, ['admin', 'admisi']);
    }

    /** Admin atau petugas ICU bisa konfirmasi bed */
    public function canManageIcu(): bool
    {
        return in_array($this->role, ['admin', 'petugas_icu']);
    }

    /** Label role untuk display */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin'          => 'Administrator',
            'admisi'         => 'Petugas Admisi',
            'petugas_icu'    => 'Petugas ICU',
            'petugas_ruang'  => 'Petugas Ruang',
            default          => $this->role,
        };
    }

    /** Warna badge role */
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
