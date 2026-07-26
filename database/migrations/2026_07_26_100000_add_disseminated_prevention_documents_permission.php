<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'slug' => 'ver_documentos_prevencion_difundibles',
            'name' => 'Ver Documentos Difundibles de Prevención',
            'description' => 'Permite a funcionarios consultar y descargar documentos de Prevención de Riesgos marcados como difundibles.',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permissions')
            ->where('slug', 'ver_documentos_prevencion_difundibles')
            ->update([
                'name' => 'Ver Documentos Difundibles de Prevención',
                'description' => 'Permite a funcionarios consultar y descargar documentos de Prevención de Riesgos marcados como difundibles.',
                'active' => true,
                'updated_at' => $now,
            ]);

        $permissionId = DB::table('permissions')
            ->where('slug', 'ver_documentos_prevencion_difundibles')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $moduleId = null;

        if (Schema::hasTable('system_modules')) {
            $parentId = DB::table('system_modules')->where('slug', 'risk_prevention')->value('id');
            $moduleId = DB::table('system_modules')
                ->where('slug', 'risk_prevention_staff_documents')
                ->value('id');

            if (! $moduleId && $parentId) {
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => 'risk_prevention_staff_documents',
                    'name' => 'Gestión documental',
                    'frontend_route' => '/risk-prevention/document-management',
                    'icon' => null,
                    'sort_order' => 8,
                    'active' => true,
                    'parent_id' => $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $moduleId = DB::table('system_modules')
                    ->where('slug', 'risk_prevention_staff_documents')
                    ->value('id');
            }

            if ($moduleId) {
                DB::table('system_modules')
                    ->where('id', $moduleId)
                    ->update([
                        'active' => true,
                        'updated_at' => $now,
                    ]);
            }
        }

        $groupId = null;

        if ($moduleId && Schema::hasTable('permission_groups')) {
            DB::table('permission_groups')->insertOrIgnore([
                'slug' => 'documentos_prevencion_funcionarios',
                'system_module_id' => $moduleId,
                'name' => 'Gestión documental para funcionarios',
                'description' => 'Consulta y descarga de documentación preventiva marcada para difusión interna.',
                'sort_order' => 135,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permission_groups')
                ->where('slug', 'documentos_prevencion_funcionarios')
                ->update([
                    'system_module_id' => $moduleId,
                    'active' => true,
                    'updated_at' => $now,
                ]);

            $groupId = DB::table('permission_groups')
                ->where('slug', 'documentos_prevencion_funcionarios')
                ->value('id');
        }

        if ($groupId && Schema::hasTable('permission_group_permission')) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
            $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

            if ($superAdminRoleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Cambio aditivo: no se eliminan permisos ni asignaciones durante rollbacks.
    }
};
