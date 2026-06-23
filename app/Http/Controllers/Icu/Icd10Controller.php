<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\RegistrasiPasien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Icd10Controller extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q     = trim($request->query('q', ''));
        $limit = min((int) $request->query('limit', 10), 50);

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $isRsus = RegistrasiPasien::rsusAvailable();
        $conn   = RegistrasiPasien::activeConnection();

        // ICD-10 hanya tersedia di SQL Server RS — lokal return kosong
        if (! $isRsus) {
            return response()->json([]);
        }

        try {
            $rows = DB::connection($conn)
                ->table('ICD10')
                ->where(function ($query) use ($q) {
                    $query->where('KODE', 'LIKE', "%{$q}%")
                          ->orWhere('KET',  'LIKE', "%{$q}%");
                })
                ->whereNotNull('KET')->where('KET', '!=', '')
                ->whereNotNull('KODE')->where('KODE', '!=', '')
                ->orderByRaw("CASE WHEN KODE LIKE ? THEN 0 ELSE 1 END", ["{$q}%"])
                ->orderBy('KODE')
                ->limit($limit)
                ->select(['KODE', 'KET'])
                ->get();

            return response()->json(
                $rows->map(fn ($r) => [
                    'kode'       => trim($r->KODE),
                    'keterangan' => trim($r->KET),
                    'label'      => trim($r->KODE) . ' — ' . trim($r->KET),
                ])->values()
            );

        } catch (\Exception $e) {
            Log::error('[Icd10Controller::search] ' . $e->getMessage());
            return response()->json([]);
        }
    }
}
