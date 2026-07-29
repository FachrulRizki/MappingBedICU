<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesRsusConnection;

class StatusKamar extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'STATUS_KAMAR';
    protected string $localTable = 'status_kamar';

    protected $primaryKey = 'Kode_Ruang';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

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

    public function ruang()
    {
        return $this->belongsTo(MRuangMaster::class, 'Kode_Ruang', 'Kode_RuangM');
    }

    public function getNamaKelasAttribute(): string
    {
        return $this->ruang?->kelas?->Nama_Kelas ?? $this->KelasBPJS ?? '-';
    }

    public function getKodeKelasAttribute(): string
    {
        return $this->ruang?->kelas?->Kode_Kelas ?? $this->KelasBPJS ?? '-';
    }

    public function isKosong(): bool
    {
        return strtoupper($this->Status) === 'KOSONG';
    }

    public function statusLabel(): string
    {
        return match (strtoupper($this->Status)) {
            'KOSONG'  => 'Tersedia',
            'BOOKING' => 'Booking',
            'ISI'     => 'Terisi',
            default   => $this->Status,
        };
    }

    /**
     * Set status bed ke BOOKING saat ICU mengalokasikan bed ke pasien.
     * Tidak mempengaruhi bed yang sudah ISI.
     */
    public static function setBooking(string $kodeRuang, string $namaUser = 'ICU'): bool
    {
        try {
            $updated = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                ->table((new static())->getTable())
                ->where('Kode_Ruang', $kodeRuang)
                ->where('Status', 'KOSONG')
                ->update([
                    'Status'    => 'BOOKING',
                    'NamaUser'  => $namaUser,
                ]);
            return $updated > 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("[StatusKamar::setBooking] {$kodeRuang}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Force set ke BOOKING tanpa peduli status sebelumnya — dipakai saat preempt.
     * Tetap tidak menyentuh bed yang statusnya ISI.
     */
    public static function setBookingForce(string $kodeRuang, string $namaUser = 'ICU'): bool
    {
        try {
            $updated = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                ->table((new static())->getTable())
                ->where('Kode_Ruang', $kodeRuang)
                ->whereIn('Status', ['KOSONG', 'BOOKING'])
                ->update([
                    'Status'   => 'BOOKING',
                    'NamaUser' => $namaUser,
                ]);
            return $updated > 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("[StatusKamar::setBookingForce] {$kodeRuang}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Release bed kembali ke KOSONG saat booking dibatalkan / pasien pindah bed.
     * Tidak mempengaruhi bed yang sudah ISI (pasien sudah masuk fisik).
     */
    public static function releaseBooking(string $kodeRuang): bool
    {
        try {
            $updated = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                ->table((new static())->getTable())
                ->where('Kode_Ruang', $kodeRuang)
                ->where('Status', 'BOOKING') // hanya release jika statusnya masih BOOKING
                ->update([
                    'Status'   => 'KOSONG',
                    'NamaUser' => null,
                ]);
            return $updated > 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("[StatusKamar::releaseBooking] {$kodeRuang}: " . $e->getMessage());
            return false;
        }
    }
}
