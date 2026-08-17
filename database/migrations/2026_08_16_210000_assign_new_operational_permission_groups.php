<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUPS = [
        [
            'slug' => 'gestion_operativa_rrhh',
            'module_slug' => 'operational_management',
            'name' => 'Gestión operativa de personas',
            'description' => 'Ausencias, saldos, selección, banco de talento e informes psicolaborales.',
            'sort_order' => 255,
            'permissions' => [
                'rrhh.ausencias.ver',
                'rrhh.ausencias.gestionar',
                'rrhh.ausencias.importar',
                'rrhh.ausencias.exportar',
                'rrhh.seleccion.ver',
                'rrhh.seleccion.gestionar',
                'rrhh.seleccion.importar',
                'rrhh.psicolaborales.confidencial',
            ],
        ],
        [
            'slug' => 'trabajo_social',
            'module_slug' => 'social_work',
            'name' => 'Trabajo Social',
            'description' => 'Casos sociales, derivaciones, alertas, prestaciones, salud, informes y auditoría.',
            'sort_order' => 258,
            'permissions' => [
                'social_work.dashboard.view',
                'social_work.students.view',
                'social_work.student_profile.view',
                'social_work.student_profile.update',
                'social_work.cases.view',
                'social_work.cases.create',
                'social_work.cases.update',
                'social_work.cases.assign',
                'social_work.cases.close',
                'social_work.cases.reopen',
                'social_work.closed_case.correct',
                'social_work.confidential.view',
                'social_work.highly_confidential.view',
                'social_work.interviews.manage',
                'social_work.actions.manage',
                'social_work.protocols.manage',
                'social_work.alerts.manage',
                'social_work.referrals.create',
                'social_work.referrals.manage',
                'social_work.referrals.submit',
                'social_work.pedagogical_reports.request',
                'social_work.pedagogical_reports.respond',
                'social_work.junaeb.manage',
                'social_work.medical.manage',
                'social_work.medical_documents.view',
                'social_work.pickup_restrictions.manage',
                'social_work.reports.create',
                'social_work.reports.approve',
                'social_work.reports.export',
                'social_work.templates.manage',
                'social_work.audit.view',
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permission_groups')
            || ! Schema::hasTable('permission_group_permission')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        foreach (self::GROUPS as $groupDefinition) {
            $moduleId = DB::table('system_modules')->where('slug', $groupDefinition['module_slug'])->value('id');
            DB::table('permission_groups')->updateOrInsert(
                ['slug' => $groupDefinition['slug']],
                [
                    'system_module_id' => $moduleId,
                    'name' => $groupDefinition['name'],
                    'description' => $groupDefinition['description'],
                    'sort_order' => $groupDefinition['sort_order'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $groupId = DB::table('permission_groups')->where('slug', $groupDefinition['slug'])->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', $groupDefinition['permissions'])
                ->where('active', true)
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
    }

    public function down(): void
    {
        // Migración deliberadamente aditiva para preservar configuraciones y auditoría en producción.
    }
};
