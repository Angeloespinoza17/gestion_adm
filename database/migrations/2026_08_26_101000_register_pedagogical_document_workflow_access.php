<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> */
    private const PERMISSIONS = [
        'pedagogical-instruments.view' => ['Ver instrumentos pedagógicos', 'Permite consultar instrumentos pedagógicos autorizados.'],
        'pedagogical-instruments.create' => ['Cargar instrumentos pedagógicos', 'Permite enviar instrumentos de evaluación a revisión documental.'],
        'pedagogical-instruments.update' => ['Rectificar instrumentos pedagógicos', 'Permite cargar nuevas versiones conservando el historial.'],
        'pedagogical-instruments.download' => ['Ver instrumentos pedagógicos', 'Permite visualizar y descargar archivos autorizados.'],
        'pedagogical-instruments.review-assigned' => ['Revisar instrumentos asignados', 'Permite consultar instrumentos de los cursos o niveles asignados a coordinación.'],
        'pedagogical-instruments.decide' => ['Resolver revisión documental', 'Permite aprobar, aprobar con observaciones o solicitar rectificación.'],
        'pedagogical-instruments.ai-report' => ['Generar informe pedagógico con IA', 'Permite solicitar un informe OpenAI para una versión del instrumento.'],
        'pedagogical-guidance.manage' => ['Gestionar documentos de orientación', 'Permite crear y habilitar reglamentos, rúbricas y orientaciones para docentes.'],
        'pedagogical-coordinators.configure' => ['Configurar alcance de coordinaciones', 'Permite asignar cursos o niveles a coordinadoras académicas.'],
        'pedagogical-print-requests.view' => ['Ver instrumentos aprobados para impresión', 'Permite consultar la cola pedagógica de Centro de Apuntes.'],
        'pedagogical-print-requests.download' => ['Descargar instrumentos aprobados', 'Permite descargar la versión aprobada desde Centro de Apuntes.'],
        'pedagogical-print-requests.print' => ['Imprimir instrumentos aprobados', 'Permite abrir la impresión directa y registrar la acción.'],
        'pedagogical-print-requests.complete' => ['Completar impresión pedagógica', 'Permite cerrar un instrumento procesado por Centro de Apuntes.'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->insertOrIgnore([
                'slug' => 'docente',
                'name' => 'Docente',
                'description' => 'Docente con acceso a sus instrumentos y rectificaciones pedagógicas.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::PERMISSIONS as $slug => [$name, $description]) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $description,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'pedagogical_management',
                'name' => 'Gestión pedagógica',
                'frontend_route' => null,
                'icon' => 'bx-book-content',
                'sort_order' => 22,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $pedagogicalParentId = DB::table('system_modules')->where('slug', 'pedagogical_management')->value('id');

            $pedagogicalModules = [
                ['slug' => 'pedagogical_my_instruments', 'name' => 'Mis instrumentos', 'frontend_route' => '/gestion-pedagogica/instrumentos', 'icon' => 'bx-file', 'sort_order' => 1],
                ['slug' => 'pedagogical_document_review', 'name' => 'Revisión documental', 'frontend_route' => '/gestion-pedagogica/revision-documental', 'icon' => 'bx-check-shield', 'sort_order' => 2],
                ['slug' => 'pedagogical_coordinator_assignments', 'name' => 'Asignar coordinadoras', 'frontend_route' => '/gestion-pedagogica/asignaciones', 'icon' => 'bx-sitemap', 'sort_order' => 3],
            ];
            foreach ($pedagogicalModules as $module) {
                DB::table('system_modules')->insertOrIgnore([
                    ...$module,
                    'active' => true,
                    'parent_id' => $pedagogicalParentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'centro_apuntes',
                'name' => 'Centro de Apuntes',
                'frontend_route' => null,
                'icon' => 'bx-printer',
                'sort_order' => 86,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $printParentId = DB::table('system_modules')->where('slug', 'centro_apuntes')->value('id');
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'centro_apuntes_pedagogical_queue',
                'name' => 'Instrumentos aprobados',
                'frontend_route' => '/centro-apuntes/instrumentos-aprobados',
                'icon' => 'bx-file-blank',
                'sort_order' => 3,
                'active' => true,
                'parent_id' => $printParentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');
            $moduleIds = DB::table('system_modules')->whereIn('slug', [
                'pedagogical_management',
                'pedagogical_my_instruments',
                'pedagogical_document_review',
                'pedagogical_coordinator_assignments',
                'centro_apuntes',
                'centro_apuntes_pedagogical_queue',
            ])->pluck('id', 'slug');

            $this->grantRoles(['docente'], [
                'pedagogical-instruments.view',
                'pedagogical-instruments.create',
                'pedagogical-instruments.update',
                'pedagogical-instruments.download',
            ], ['pedagogical_management', 'pedagogical_my_instruments'], $permissionIds, $moduleIds, $now);

            $this->grantRoles(['coordinadora_academica'], [
                'pedagogical-instruments.view',
                'pedagogical-instruments.review-assigned',
                'pedagogical-instruments.download',
                'pedagogical-instruments.decide',
                'pedagogical-instruments.ai-report',
                'pedagogical-guidance.manage',
            ], ['pedagogical_management', 'pedagogical_document_review'], $permissionIds, $moduleIds, $now);

            $this->grantRoles(['coordinador_academico', 'jefe_utp'], [
                'pedagogical-instruments.view',
                'pedagogical-instruments.review-assigned',
                'pedagogical-instruments.download',
                'pedagogical-instruments.decide',
                'pedagogical-instruments.ai-report',
                'pedagogical-guidance.manage',
                'pedagogical-coordinators.configure',
            ], ['pedagogical_management', 'pedagogical_document_review', 'pedagogical_coordinator_assignments'], $permissionIds, $moduleIds, $now);

            $this->grantRoles(['super_admin', 'superadmin', 'administrador', 'direccion'], array_keys(self::PERMISSIONS), [
                'pedagogical_management',
                'pedagogical_my_instruments',
                'pedagogical_document_review',
                'pedagogical_coordinator_assignments',
                'centro_apuntes',
                'centro_apuntes_pedagogical_queue',
            ], $permissionIds, $moduleIds, $now);

            $this->grantPrintCenterRoles($permissionIds, $moduleIds, $now);
            $this->attachPermissionGroups($permissionIds, $pedagogicalParentId, $printParentId, $now);
        });
    }

    public function down(): void
    {
        // Cambio aditivo: no elimina roles, permisos, módulos ni asignaciones vigentes.
    }

    private function grantRoles(array $roleSlugs, array $permissionSlugs, array $moduleSlugs, $permissionIds, $moduleIds, mixed $now): void
    {
        $roleIds = DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id');
        foreach ($roleIds as $roleId) {
            if (Schema::hasTable('permission_role')) {
                foreach ($permissionSlugs as $slug) {
                    if ($permissionId = $permissionIds[$slug] ?? null) {
                        DB::table('permission_role')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
            if (Schema::hasTable('role_system_module')) {
                foreach ($moduleSlugs as $slug) {
                    if ($moduleId = $moduleIds[$slug] ?? null) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $roleId,
                            'system_module_id' => $moduleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    private function grantPrintCenterRoles($permissionIds, $moduleIds, mixed $now): void
    {
        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $operationalPermissionId = DB::table('permissions')->where('slug', 'cambiar_estado_solicitud_impresion')->value('id');
        $roleIds = $operationalPermissionId
            ? DB::table('permission_role')->where('permission_id', $operationalPermissionId)->pluck('role_id')->unique()
            : collect();

        foreach ($roleIds as $roleId) {
            foreach (['pedagogical-print-requests.view', 'pedagogical-print-requests.download', 'pedagogical-print-requests.print', 'pedagogical-print-requests.complete'] as $slug) {
                if ($permissionId = $permissionIds[$slug] ?? null) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            if (Schema::hasTable('role_system_module')) {
                foreach (['centro_apuntes', 'centro_apuntes_pedagogical_queue'] as $slug) {
                    if ($moduleId = $moduleIds[$slug] ?? null) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $roleId,
                            'system_module_id' => $moduleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    private function attachPermissionGroups($permissionIds, mixed $pedagogicalParentId, mixed $printParentId, mixed $now): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $groups = [
            'gestion_pedagogica' => [$pedagogicalParentId, array_keys(self::PERMISSIONS)],
            'centro_apuntes_panol' => [$printParentId, [
                'pedagogical-print-requests.view',
                'pedagogical-print-requests.download',
                'pedagogical-print-requests.print',
                'pedagogical-print-requests.complete',
            ]],
        ];
        foreach ($groups as $slug => [$moduleId, $slugs]) {
            $groupId = DB::table('permission_groups')->where('slug', $slug)->value('id');
            if (! $groupId) {
                continue;
            }
            foreach ($slugs as $permissionSlug) {
                if ($permissionId = $permissionIds[$permissionSlug] ?? null) {
                    DB::table('permission_group_permission')->insertOrIgnore([
                        'permission_group_id' => $groupId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
