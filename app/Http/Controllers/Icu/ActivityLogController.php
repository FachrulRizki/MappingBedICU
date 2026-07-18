<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user    = Auth::user();

        // Cek permission dari session Keycloak — bukan hardcode role.
        // Siapapun yang punya 'activity_log:view' bisa lihat semua log (termasuk role baru).
        // Yang tidak punya permission ini pun tidak bisa masuk ke sini karena route sudah
        // dijaga middleware: permission:activity_log:view
        $permissions   = $request->session()->get('keycloak_permissions', []);
        $canViewAll    = in_array('activity_log:view', $permissions, true);

        $tglDari = $request->query('tgl_dari',   '');
        $tglAkh  = $request->query('tgl_sampai', '');
        $jenis   = $request->query('jenis',      '');
        $userId  = $request->query('user_id',    '');
        $perPage = (int) $request->query('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100]) ? $perPage : 50;

        $q = ActivityLog::query()->latest('created_at');

        // Tanpa canViewAll → hanya bisa lihat log sendiri
        if (! $canViewAll) {
            $q->where('user_id', $user->id);
        } elseif ($userId !== '') {
            $q->where('user_id', (int) $userId);
        }

        if ($jenis !== '') {
            $q->where('jenis_aktivitas', $jenis);
        }

        if ($tglDari !== '' && $tglAkh !== '') {
            $q->whereBetween('created_at', [$tglDari . ' 00:00:00', $tglAkh . ' 23:59:59']);
        } elseif ($tglDari !== '') {
            $q->where('created_at', '>=', $tglDari . ' 00:00:00');
        } elseif ($tglAkh !== '') {
            $q->where('created_at', '<=', $tglAkh . ' 23:59:59');
        }

        $logs = $q->paginate($perPage)->through(fn (ActivityLog $log) => [
            'id'              => $log->id,
            'user_name'       => $log->user_name        ?? '—',
            'user_role'       => $log->user_role        ?? null,
            'jenis_aktivitas' => $log->jenis_aktivitas,
            'aktivitas'       => $log->aktivitas,
            'module'          => $log->module,
            'subject_id'      => $log->subject_id,
            'ip_address'      => $log->ip_address       ?? '—',
            'created_at'      => $log->created_at
                ? $log->created_at->setTimezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB'
                : '—',
        ]);

        // Daftar user untuk filter dropdown — hanya jika punya permission lihat semua
        $users = $canViewAll
            ? User::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role])
                ->values()
            : [];

        return Inertia::render('Icu/ActivityLog', [
            'logs'         => $logs,
            'jenisOptions' => ActivityLog::jenisOptions(),
            'users'        => $users,
            'isAdmin'      => $canViewAll,
            'filters'      => [
                'tglDari'  => $tglDari,
                'tglAkh'   => $tglAkh,
                'jenis'    => $jenis,
                'userId'   => $userId,
                'perPage'  => $perPage,
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }
}
