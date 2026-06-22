<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Services\Icu\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(Request $request): Response
    {
        $today = now()->format('Y-m-d');

        $filters = [
            'tgl_dari'   => $request->query('tgl_dari',   $today),
            'tgl_sampai' => $request->query('tgl_sampai', $today),
            'search'     => $request->query('search',     ''),
        ];

        return Inertia::render('Dashboard', [
            ...$this->dashboardService->getDashboardData(auth()->user(), $filters),
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }
}
