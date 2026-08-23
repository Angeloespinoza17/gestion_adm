<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const ACCOUNTING_PERMISSIONS = [
        'contabilidad.ejecucion_presupuestaria.ver' => 'Ver ejecución presupuestaria',
        'contabilidad.ejecucion_presupuestaria.importar' => 'Importar ejecución presupuestaria',
        'contabilidad.ejecucion_presupuestaria.exportar' => 'Exportar ejecución presupuestaria',
    ];

    /** @var array<string, string> */
    private const CURRICULUM_PERMISSIONS = [
        'libro_digital.curriculum_programs.view' => 'Consultar catálogo de programas curriculares',
        'libro_digital.curriculum_programs.documents.view' => 'Consultar documentos ministeriales protegidos',
        'libro_digital.curriculum_programs.import' => 'Importar libros curriculares PDF',
        'libro_digital.curriculum_programs.import_batch' => 'Importar lotes de libros curriculares PDF',
        'libro_digital.curriculum_programs.review' => 'Revisar candidatos de libros curriculares',
        'libro_digital.curriculum_programs.resolve_conflicts' => 'Resolver conflictos de importación curricular',
        'libro_digital.curriculum_programs.publish' => 'Publicar programas curriculares validados',
        'libro_digital.curriculum_programs.archive' => 'Archivar importaciones y programas sin uso',
        'libro_digital.curriculum_programs.reprocess' => 'Reprocesar importaciones curriculares fallidas',
        'libro_digital.curriculum_programs.export_pdf' => 'Exportar programas curriculares a PDF',
    ];

    /** @var array<int, string> */
    private const RISK_PERMISSIONS = [
        'risk-matrix.view',
        'risk-matrix.create',
        'risk-matrix.update',
        'risk-matrix.delete-draft',
        'risk-matrix.submit',
        'risk-matrix.review',
        'risk-matrix.observe',
        'risk-matrix.approve',
        'risk-matrix.archive',
        'risk-matrix.create-version',
        'risk-matrix.import',
        'risk-matrix.export',
        'risk-matrix.manage-catalogs',
        'risk-matrix.view-audit',
        'risk-matrix.override-block',
        'risk-control.create',
        'risk-control.update',
        'risk-control.assign',
        'risk-control.implement',
        'risk-control.verify',
        'preventive-program.view',
        'preventive-program.manage',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $newPermissions = self::ACCOUNTING_PERMISSIONS + self::CURRICULUM_PERMISSIONS;

            foreach ($newPermissions as $slug => $name) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => 'Permiso incorporado por una migración productiva aditiva.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->assignPermissionGroups($now);
            $this->assignAccountingAccess($now);
            $this->assignCurriculumAccess($now);
        });
    }

    public function down(): void
    {
        // Forward-only: ningún permiso o acceso productivo se elimina al revertir.
    }

    private function assignPermissionGroups($now): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $groups = [
            'contabilidad' => array_keys(self::ACCOUNTING_PERMISSIONS),
            'libro_digital' => array_keys(self::CURRICULUM_PERMISSIONS),
            'prevencion_riesgos' => self::RISK_PERMISSIONS,
        ];

        foreach ($groups as $groupSlug => $permissionSlugs) {
            $groupId = DB::table('permission_groups')->where('slug', $groupSlug)->value('id');
            if (! $groupId) {
                continue;
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
    }

    private function assignAccountingAccess($now): void
    {
        $rolePermissions = [
            'super_admin' => array_keys(self::ACCOUNTING_PERMISSIONS),
            'contabilidad_admin' => array_keys(self::ACCOUNTING_PERMISSIONS),
            'contabilidad_analista' => array_keys(self::ACCOUNTING_PERMISSIONS),
            'direccion' => [
                'contabilidad.ejecucion_presupuestaria.ver',
                'contabilidad.ejecucion_presupuestaria.exportar',
            ],
            'solo_lectura_contabilidad' => [
                'contabilidad.ejecucion_presupuestaria.ver',
                'contabilidad.ejecucion_presupuestaria.exportar',
            ],
        ];

        $this->assignRolePermissions($rolePermissions, $now);

        if (! Schema::hasTable('role_system_module') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $moduleIds = DB::table('system_modules')
            ->whereIn('slug', ['accounting', 'accounting_budget_execution'])
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', array_keys($rolePermissions))
            ->pluck('id');

        foreach ($roleIds as $roleId) {
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

    private function assignCurriculumAccess($now): void
    {
        $all = array_keys(self::CURRICULUM_PERMISSIONS);
        $view = [
            'libro_digital.curriculum_programs.view',
            'libro_digital.curriculum_programs.documents.view',
        ];
        $viewAndExport = [...$view, 'libro_digital.curriculum_programs.export_pdf'];
        $review = [...$view, 'libro_digital.curriculum_programs.review', 'libro_digital.curriculum_programs.resolve_conflicts', 'libro_digital.curriculum_programs.publish', 'libro_digital.curriculum_programs.export_pdf'];

        $this->assignRolePermissions([
            'super_admin' => $all,
            'direccion' => $all,
            'coordinador_academico' => $all,
            'jefe_utp' => $all,
            'subdirector' => $review,
            'sostenedor' => $viewAndExport,
            'docente' => $viewAndExport,
            'profesor_jefe' => $view,
            'educador_parvulos' => $view,
        ], $now);
    }

    /**
     * @param  array<string, array<int, string>>  $rolePermissions
     */
    private function assignRolePermissions(array $rolePermissions, $now): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach (DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
