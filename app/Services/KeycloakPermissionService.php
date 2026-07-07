<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class KeycloakPermissionService
{
    public function extractPermissionsFromToken(array $tokenPayload): array
    {
        $permissions = $tokenPayload['authorization']['permissions'] ?? [];

        if (empty($permissions)) {
            Log::debug('[KeycloakPermission] Tidak ada claim authorization.permissions di JWT.');
            return [];
        }

        $result = [];

        foreach ($permissions as $permission) {
            $resource = $permission['rsname'] ?? null;
            $scopes   = $permission['scopes'] ?? [];

            if (! $resource) {
                continue;
            }

            $prefix = $this->normalizeResourceName($resource);

            foreach ($scopes as $scope) {
                $result[] = "{$prefix}:{$scope}";
            }
        }

        Log::debug('[KeycloakPermission] Permissions diekstrak: ' . implode(', ', $result));

        return array_unique($result);
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