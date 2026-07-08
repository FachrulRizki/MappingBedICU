<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class KeycloakPermissionService
{
    public function extractPermissionsFromToken(array $tokenPayload): array
    {
        $authPermissions = [];
        foreach ($tokenPayload['authorization']['permissions'] ?? [] as $permission) {
            $resource = $permission['Resource Set Name'] ?? null;
            if (! $resource) continue;

            $prefix = $this->normalizeResourceName($resource);
            foreach ($permission['scopes'] ?? [] as $scope) {
                $authPermissions[] = "{$prefix}:{$scope}";
            }
        }
        $authPermissions = array_values(array_unique($authPermissions));

        if (! empty($authPermissions)) {
            Log::debug('[KeycloakPermission] Permissions dari Authorization Services: ' . implode(', ', $authPermissions));
            return $authPermissions;
        }

        $clientId = config('services.keycloak.client_id', 'icu-bed');
        $roles    = $tokenPayload['resource_access'][$clientId]['roles'] ?? [];
        $clientPermissions = array_values(array_unique(
            array_filter($roles, fn ($role) => str_contains($role, ':'))
        ));

        if (! empty($clientPermissions)) {
            Log::debug('[KeycloakPermission] Permissions dari client roles: ' . implode(', ', $clientPermissions));
            return $clientPermissions;
        }

        Log::debug('[KeycloakPermission] Tidak ada permissions di JWT (authorization.permissions maupun client roles).');
        return [];
    }

    /**
     * Cek apakah user punya permission tertentu.
     *
     * @param  array  $userPermissions  Array permissions dari session
     * @param  string $permission       Permission yang dicek, contoh: "booking_ext:create"
     */
    public function hasPermission(array $userPermissions, string $permission): bool
    {
        return in_array($permission, $userPermissions, true);
    }

    /**
     * Cek apakah user punya salah satu dari beberapa permissions.
     *
     * @param  array  $userPermissions  Array permissions dari session
     * @param  array  $permissions      Permissions yang dicek (OR logic)
     */
    public function hasAnyPermission(array $userPermissions, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($userPermissions, $permission)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeResourceName(string $resource): string
    {
        $map = [
            'booking-external' => 'booking_ext',
            'booking-internal' => 'booking_int',
            'dashboard'        => 'dashboard',
            'denah-bed'        => 'denah_bed',
            'settings-users'   => 'settings_users',
            'settings-roles'   => 'settings_roles',
            'activity-log'     => 'activity_log',
        ];

        return $map[$resource] ?? str_replace('-', '_', $resource);
    }
}