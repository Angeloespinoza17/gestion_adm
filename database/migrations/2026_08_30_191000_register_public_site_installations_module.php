<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        $permissions = [
            'ver_instalaciones_sitio' => 'Ver Instalaciones del Sitio Web',
            'gestionar_instalaciones_sitio' => 'Gestionar Instalaciones del Sitio Web',
        ];

        foreach ($permissions as $slug => $name) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();

            if (! $permission) {
                DB::table('permissions')->insert([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $parentId = DB::table('system_modules')->where('slug', 'public_site')->value('id');

        if (! $parentId) {
            DB::table('system_modules')->insert([
                'slug' => 'public_site',
                'name' => 'Sitio web',
                'frontend_route' => null,
                'icon' => 'bx-globe',
                'sort_order' => 119,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'public_site')->value('id');
        }

        if (! DB::table('system_modules')->where('slug', 'public_site_installations')->exists()) {
            DB::table('system_modules')->insert([
                'slug' => 'public_site_installations',
                'name' => 'Instalaciones',
                'frontend_route' => '/admin/instalaciones',
                'icon' => null,
                'sort_order' => 9,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->attachToPermissionGroup(array_keys($permissions), $now);
        $this->grantDefaultAccess(array_keys($permissions), $now);
    }

    /**
     * RBAC catalog records and assignments are intentionally retained on rollback.
     */
    public function down(): void
    {
        // Additive and non-destructive by design.
    }

    private function attachToPermissionGroup(array $permissionSlugs, $now): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $groupId = DB::table('permission_groups')->where('slug', 'sitio_publico')->value('id');

        if (! $groupId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function grantDefaultAccess(array $permissionSlugs, $now): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('permission_role')
            || ! Schema::hasTable('role_system_module')
        ) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['super_admin', 'superadmin', 'administrador', 'direccion'])
            ->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
        $moduleIds = DB::table('system_modules')
            ->whereIn('slug', ['public_site', 'public_site_installations'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($moduleIds as $moduleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
