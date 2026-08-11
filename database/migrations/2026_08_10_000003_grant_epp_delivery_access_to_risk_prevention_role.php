<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('system_modules')
        ) {
            return;
        }

        $now = now();
        $permissions = [
            [
                'slug' => 'ver_prevencion_riesgos',
                'name' => 'Ver Prevención de Riesgos',
                'description' => 'Permite acceder al módulo de Prevención de Riesgos y consultar entregas de EPP.',
            ],
            [
                'slug' => 'gestionar_prevencion_riesgos',
                'name' => 'Gestionar Prevención de Riesgos',
                'description' => 'Permite registrar y administrar entregas de EPP y los demás registros de Prevención de Riesgos.',
            ],
            [
                'slug' => 'exportar_prevencion_riesgos',
                'name' => 'Exportar Prevención de Riesgos',
                'description' => 'Permite exportar reportes y listados de Prevención de Riesgos.',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                ...$permission,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')
                ->where('slug', $permission['slug'])
                ->update([
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'active' => true,
                    'updated_at' => $now,
                ]);
        }

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'risk_prevention',
            'name' => 'Prevención de Riesgos',
            'frontend_route' => '/risk-prevention',
            'icon' => 'bx-shield-quarter',
            'sort_order' => 80,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $parentId = DB::table('system_modules')
            ->where('slug', 'risk_prevention')
            ->value('id');

        if (! $parentId) {
            return;
        }

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'risk_prevention_epp',
            'name' => 'EPP y seguridad',
            'frontend_route' => '/risk-prevention/epp',
            'icon' => null,
            'sort_order' => 5,
            'active' => true,
            'parent_id' => $parentId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('system_modules')
            ->where('slug', 'risk_prevention_epp')
            ->update([
                'name' => 'EPP y seguridad',
                'frontend_route' => '/risk-prevention/epp',
                'sort_order' => 5,
                'active' => true,
                'parent_id' => $parentId,
                'updated_at' => $now,
            ]);

        $roleId = DB::table('roles')
            ->where('slug', 'prevencion_riesgos')
            ->value('id');

        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_column($permissions, 'slug'))
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('role_system_module')) {
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', ['risk_prevention', 'risk_prevention_epp'])
                ->pluck('id');

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

    public function down(): void
    {
        // Cambio aditivo: no se eliminan accesos que podrían estar en uso.
    }
};
