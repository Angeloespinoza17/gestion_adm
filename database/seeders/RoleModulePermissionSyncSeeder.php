<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Services\Rbac\RoleModuleSyncService;
use Illuminate\Database\Seeder;

class RoleModulePermissionSyncSeeder extends Seeder
{
    public function run(): void
    {
        $syncService = app(RoleModuleSyncService::class);

        Role::query()
            ->with('permissions:id')
            ->get()
            ->each(fn (Role $role) => $syncService->syncRoleModulesFromPermissions($role));
    }
}
