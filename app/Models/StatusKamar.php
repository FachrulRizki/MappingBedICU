<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status occupancy bed real-time.
 * PK: Kode_Ruang (sama dengan Kode_RuangM di M_RUANG_MASTER)
 *
 * Untuk mendapat jenis bed (ICUNV/HCU/ICU/CVCU):
 *   $statusKamar->ruang->kelas->Kode_Kelas   → 'ICUNV'
 *   $statusKamar->ruang->kelas->Nama_Kelas   → 'ICU BPJS' dll
 *   $statusKamar->ruang->kelas->Kelas        → 'ICU'
 *
 * Status uppercase mengikuti DB existing: KOSONG | ISI | BOOKING
 */
class StatusKamar extends Model
{
    protected $table      = 'status_kamar';
    protected $primaryKey = 'Kode_Ruang';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'Kode_Ruang',
        'Kode_Bangsal',
        'Status',
        'Keterangan',
        'NamaUser',
        'KelasBPJS',
        'No_MR',
        'Oksigen',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────

    /**
     * Ruang master → dari sini bisa dapat Kode_Kelas dan Nama_Kelas
     * STATUS_KAMAR.Kode_Ruang → M_RUANG_MASTER.Kode_RuangM
     */
    public function ruang()
    {
        return $this->belongsTo(MRuangMaster::class, 'Kode_Ruang', 'Kode_RuangM');
    }

    /**
     * Shortcut: langsung ke M_KELAS via M_RUANG_MASTER
     * Gunakan: $statusKamar->kelas->Kode_Kelas
     */
    public function kelas()
    {
        return $this->hasOneThrough(
            MKelas::class,
            MRuangMaster::class,
            'Kode_RuangM',  // FK di m_ruang_master → status_kamar
            'Kode_Kelas',   // FK di m_kelas
            'Kode_Ruang',   // PK lokal status_kamar
            'Kode_Kelas'    // FK lokal m_ruang_master
        );
    }

    /** ICU admision yang sedang menempati bed ini */
    public function icuAdmision()
    {
        return $this->hasOne(IcuAdmision::class, 'allocated_bed_id', 'Kode_Ruang');
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    /**
     * Nama kelas bed dari join M_RUANG_MASTER → M_KELAS.
     * Perlu eager load: with('ruang.kelas')
     */
    public function getNamaKelasAttribute(): string
    {
        return $this->ruang?->kelas?->Nama_Kelas ?? $this->KelasBPJS ?? '-';
    }

    /**
     * Kode kelas bed (ICUNV, HCU, ICU, CVCU, dst.)
     * Ini yang dipakai untuk matching required_bed_type.
     */
    public function getKodeKelasAttribute(): string
    {
        return $this->ruang?->kelas?->Kode_Kelas ?? $this->KelasBPJS ?? '-';
    }

    public function isKosong(): bool
    {
        return strtoupper($this->Status) === 'KOSONG';
    }

    public function statusBadge(): string
    {
        return match (strtoupper($this->Status)) {
            'KOSONG'  => 'success',
            'BOOKING' => 'warning',
            'ISI'     => 'danger',
            default   => 'secondary',
        };
    }

    public function statusLabel(): string
    {
        return match (strtoupper($this->Status)) {
            'KOSONG'  => 'Kosong',
            'BOOKING' => 'Booking',
            'ISI'     => 'Terisi',
            default   => $this->Status,
        };
    }
}
