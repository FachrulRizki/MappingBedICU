<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'role'          => $user->role,
                    'role_label'    => $user->roleLabel(),
                    'role_color'    => $user->roleColor(),
                    'unit_kerja'    => $user->unit_kerja,
                    'auth_provider' => $user->auth_provider ?? 'local',
                    'ward_ids'      => $user->getWardIdsArray(),
                    'permissions'   => $request->session()->get('keycloak_permissions', []),
                ] : null,
            ],
            'rsus_available' => app(\App\Services\RsusConnectionService::class)->isAvailable(),
        ];
    }
}
