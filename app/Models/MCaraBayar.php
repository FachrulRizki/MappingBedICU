<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\Concerns\UsesRsusConnection;

class MCaraBayar extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'M_CARABAYAR';
    protected string $localTable = 'm_carabayar';

    protected $primaryKey = 'KODE_BAYAR';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = [
        'KODE_BAYAR',
        'KET_BAYAR',
    ];

    /**
     * Ambil semua cara bayar sebagai list [kode, nama].
     */
    public static function list(): \Illuminate\Support\Collection
    {
        try {
            return static::orderBy('KODE_BAYAR')
                ->whereNotIn('KET_BAYAR', ['COVID'])
                ->get(['KODE_BAYAR', 'KET_BAYAR'])
                ->map(fn($row) => [
                    'kode' => $row->KODE_BAYAR,
                    'nama' => $row->KET_BAYAR,
                ])
                ->values();
        } catch (\Exception $e) {
            Log::error('[MCaraBayar::list] ' . $e->getMessage());
            return collect();
        }
    }
}
