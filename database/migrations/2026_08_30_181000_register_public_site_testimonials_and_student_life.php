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
            'ver_testimonios' => 'Ver Testimonios del Sitio Web',
            'gestionar_testimonios' => 'Gestionar Testimonios del Sitio Web',
            'ver_vida_estudiantil' => 'Ver Vida Estudiantil del Sitio Web',
            'gestionar_vida_estudiantil' => 'Gestionar Vida Estudiantil del Sitio Web',
        ];

        foreach ($permissions as $slug => $name) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
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

        if (! DB::table('system_modules')->where('slug', 'public_site')->exists()) {
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
        }

        $parentId = DB::table('system_modules')->where('slug', 'public_site')->value('id');
        $modules = [
            'public_site_testimonials' => [
                'name' => 'Testimonios',
                'frontend_route' => '/admin/testimonios',
                'sort_order' => 4,
            ],
            'public_site_student_life' => [
                'name' => 'Vida estudiantil',
                'frontend_route' => '/admin/vida-estudiantil',
                'sort_order' => 5,
            ],
        ];

        foreach ($modules as $slug => $module) {
            if (! DB::table('system_modules')->where('slug', $slug)->exists()) {
                DB::table('system_modules')->insert([
                    'slug' => $slug,
                    'name' => $module['name'],
                    'frontend_route' => $module['frontend_route'],
                    'icon' => null,
                    'sort_order' => $module['sort_order'],
                    'active' => true,
                    'parent_id' => $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->attachToPermissionGroup($permissions, $now);
        $this->grantDefaultAccess(array_keys($permissions), array_keys($modules), $now);
    }

    /**
     * RBAC catalogs can predate this feature and may have been customized.
     * Rollback intentionally keeps these additive records and assignments.
     */
    public function down(): void
    {
        // Intentionally additive and non-destructive.
    }

    private function attachToPermissionGroup(array $permissions, $now): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $groupId = DB::table('permission_groups')->where('slug', 'sitio_publico')->value('id');

        if (! $groupId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_keys($permissions))
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
