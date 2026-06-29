<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id'            => $request->user()->id,
                    'name'          => $request->user()->name,
                    'email'         => $request->user()->email,
                    'role'          => $request->user()->role,
                    'role_label'    => $request->user()->roleLabel(),
                    'role_color'    => $request->user()->roleColor(),
                    'unit_kerja'    => $request->user()->unit_kerja,
                    'auth_provider' => $request->user()->auth_provider ?? 'local',
                    'ward_ids'      => $request->user()->getWardIdsArray(),
                    // Permissions dari Keycloak Authorization Services.
                    // Kosong [] sebelum Keycloak Authorization diaktifkan — Vue handle gracefully.
                    'permissions'   => $request->session()->get('keycloak_permissions', []),
                ] : null,
            ],
            // Status koneksi SQL Server RS — dipakai Vue untuk show/hide fitur yang butuh data RS
            'rsus_available' => app(\App\Services\RsusConnectionService::class)->isAvailable(),
        ];
    }
}