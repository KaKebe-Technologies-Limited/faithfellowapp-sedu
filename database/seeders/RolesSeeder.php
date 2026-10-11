<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Platform roles (Spatie). Church sub-roles live in church_roles and are
 * created per church from config('faithfellows.role_presets').
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $platformPermissions = [
            'platform.churches.manage',
            'platform.billing.manage',
            'platform.users.manage',
            'platform.moderate',
            'platform.support',
            'platform.settings',
            'platform.impersonate',
            'platform.analytics',
        ];

        foreach ($platformPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (config('faithfellows.platform_roles') as $role) {
            Role::findOrCreate($role, 'web');
        }

        Role::findByName('super_admin', 'web')->syncPermissions($platformPermissions);
    }
}
