<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesRsusConnection;

class MCaraBayar extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'M_CARABAYAR';
    protected string $localTable = 'm_carabayar';

    protected $primaryKey = 'KODE_BAYAR';
    public    $incrementing = false;
    protected $keyType     = 'string';
    public    $timestamps  = false;

    protected $fillable = [
        'KODE_BAYAR',
        'KET_BAYAR',
    ];

    /**
     * Ambil semua cara bayar sebagai list [kode, nama].
     * Di-cache 10 menit — data ini sangat jarang berubah.
     * Disimpan sebagai plain array agar aman di semua cache driver (file, redis, database).
     */
    public static function list(): \Illuminate\Support\Collection
    {
        $data = \Illuminate\Support\Facades\Cache::remember('m_cara_bayar_list', 600, function () {
            try {
                return static::orderBy('KODE_BAYAR')
                    ->whereNotIn('KET_BAYAR', ['COVID'])
                    ->get(['KODE_BAYAR', 'KET_BAYAR'])
                    ->map(fn($row) => [
                        'kode' => $row->KODE_BAYAR,
                        'nama' => $row->KET_BAYAR,
                    ])
                    ->values()
                    ->toArray(); // plain array — aman di-serialize
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('[MCaraBayar::list] ' . $e->getMessage());
                return [];
            }
        });

        return collect(is_array($data) ? $data : []);
    }
}
