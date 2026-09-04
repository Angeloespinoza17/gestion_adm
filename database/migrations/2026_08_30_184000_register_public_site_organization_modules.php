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
            'ver_cgpa_sitio' => 'Ver CGPA del Sitio Web',
            'gestionar_cgpa_sitio' => 'Gestionar CGPA del Sitio Web',
            'ver_cde_sitio' => 'Ver CDE del Sitio Web',
            'gestionar_cde_sitio' => 'Gestionar CDE del Sitio Web',
            'ver_comite_paritario_sitio' => 'Ver Comité Paritario del Sitio Web',
            'gestionar_comite_paritario_sitio' => 'Gestionar Comité Paritario del Sitio Web',
        ];

        foreach ($permissions as $slug => $name) {
            $exists = DB::table('permissions')->where('slug', $slug)->exists();
            if (! $exists) {
                DB::table('permissions')->insert([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('permissions')->where('slug', $slug)->update([
                    'name' => $name,
                    'active' => true,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! DB::table('system_modules')->where('slug', 'public_site')->exists()) {
            DB::table('system_modules')->insert([
                'slug' => 'public_site',
                'name' => 'Sitio web',
                'frontend_route' => null,
                'icon' => 'bx-globe',
                'sort_order' => 119,
                'active' => true,
                'parent_id' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        } else {
            DB::table('system_modules')->where('slug', 'public_site')->update([
                'name' => 'Sitio web',
                'icon' => 'bx-globe',
                'sort_order' => 119,
                'active' => true,
                'updated_at' => $now,
            ]);
        }

        $parentId = DB::table('system_modules')->where('slug', 'public_site')->value('id');
        $modules = [
            'public_site_cgpa' => ['CGPA', '/admin/cgpa', 6],
            'public_site_cde' => ['CDE', '/admin/cde', 7],
            'public_site_joint_committee' => ['Comité Paritario', '/admin/comite-paritario', 8],
        ];

        foreach ($modules as $slug => [$name, $route, $sortOrder]) {
            if (! DB::table('system_modules')->where('slug', $slug)->exists()) {
                DB::table('system_modules')->insert([
                    'slug' => $slug,
                    'name' => $name,
                    'frontend_route' => $route,
                    'icon' => null,
                    'sort_order' => $sortOrder,
                    'active' => true,
                    'parent_id' => $parentId,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            } else {
                DB::table('system_modules')->where('slug', $slug)->update([
                    'name' => $name,
                    'frontend_route' => $route,
                    'sort_order' => $sortOrder,
                    'active' => true,
                    'parent_id' => $parentId,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->attachToGroup(array_keys($permissions), $now);
        $this->grantDefaultAccess(array_keys($permissions), array_keys($modules), $now);
    }

    public function down(): void
    {
        // RBAC records are intentionally retained to keep rollback additive and non-destructive.
    }

    private function attachToGroup(array $permissionSlugs, $now): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $groupId = DB::table('permission_groups')->where('slug', 'sitio_publico')->value('id');
        if (! $groupId) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id') as $permissionId) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function grantDefaultAccess(array $permissionSlugs, array $moduleSlugs, $now): void
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
            ->whereIn('slug', array_merge(['public_site'], $moduleSlugs))
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
