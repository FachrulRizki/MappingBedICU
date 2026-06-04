<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Services\Icu\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Tampilkan dashboard monitoring alur pasien ICU.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            ...$this->dashboardService->getDashboardData(),
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }
}
