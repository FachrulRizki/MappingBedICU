<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'IB_activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'jenis_aktivitas',
        'aktivitas',
        'module',
        'subject_id',
        'subject_type',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    // Relasi

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForModule(Builder $q, string $module): Builder
    {
        return $q->where('module', $module);
    }

    public function scopeForJenis(Builder $q, string $jenis): Builder
    {
        return $q->where('jenis_aktivitas', $jenis);
    }

    public function scopeInDateRange(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
    }

    public static function jenisOptions(): array
    {
        return [
            'Autentikasi',
            'Buat Data',
            'Setujui Data',
            'Konfirmasi Bed',
            'Verifikasi Bed',
            'Verifikasi Pasien',
            'Waiting List',
            'Tolak Data',
        ];
    }

    /** Label warna per jenis untuk tampilan badge di Vue */
    public static function jenisColors(): array
    {
        return [
            'Autentikasi'        => ['bg' => 'rgba(59,130,246,.12)',  'color' => '#3B82F6'],
            'Buat Data'          => ['bg' => 'rgba(0,168,132,.12)',   'color' => '#00A884'],
            'Setujui Data'       => ['bg' => 'rgba(16,185,129,.12)',  'color' => '#059669'],
            'Konfirmasi Bed'     => ['bg' => 'rgba(14,165,233,.12)',  'color' => '#0EA5E9'],
            'Verifikasi Bed'     => ['bg' => 'rgba(99,102,241,.12)',  'color' => '#6366F1'],
            'Verifikasi Pasien'  => ['bg' => 'rgba(245,158,11,.12)',  'color' => '#D97706'],
            'Waiting List'       => ['bg' => 'rgba(249,115,22,.12)',  'color' => '#EA580C'],
            'Tolak Data'         => ['bg' => 'rgba(239,68,68,.12)',   'color' => '#DC2626'],
        ];
    }
}
