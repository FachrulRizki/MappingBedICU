<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\RegistrasiPasien;

class Icd10Controller extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q     = trim($request->query('q', ''));
        $limit = min((int) $request->query('limit', 10), 50);

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        if (! RegistrasiPasien::rsusAvailable()) {
            return response()->json([]);
        }

        try {
            $rows = DB::connection('sqlsrv_rsus')
                ->table('ICD10')
                ->where(function ($query) use ($q) {
                    $query->where('KODE', 'LIKE', "%{$q}%")
                          ->orWhere('KET',  'LIKE', "%{$q}%");
                })
                ->whereNotNull('KET')
                ->where('KET', '!=', '')
                ->whereNotNull('KODE')
                ->where('KODE', '!=', '')
                ->orderByRaw("CASE WHEN KODE LIKE ? THEN 0 ELSE 1 END", ["{$q}%"])
                ->orderBy('KODE')
                ->limit($limit)
                ->select(['KODE', 'KET'])
                ->get();

            return response()->json(
                $rows->map(fn($r) => [
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
