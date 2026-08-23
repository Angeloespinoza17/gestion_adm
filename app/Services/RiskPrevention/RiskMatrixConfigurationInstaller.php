<?php

namespace App\Services\RiskPrevention;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiskMatrixConfigurationInstaller
{
    public function install(): void
    {
        $now = now();

        // RBAC y navegación son independientes del esquema operacional IPER.
        // Así una instalación parcial nunca oculta el módulo ni pierde accesos.
        $this->installRbac($now);

        if (! Schema::hasTable('prevent_risk_methodologies') || ! Schema::hasTable('prevent_risk_catalog_items')) {
            return;
        }

        $methodologyId = DB::table('prevent_risk_methodologies')->updateOrInsert(
            ['code' => 'ISP-2025-VEP', 'version_number' => 1],
            [
                'name' => 'Metodología IPER VEP institucional',
                'description' => 'Evaluación de riesgos mediante Probabilidad x Consecuencia, con catálogos versionados.',
                'valid_from' => '2025-01-01',
                'valid_until' => null,
                'active' => true,
                'configuration' => json_encode([
                    'formula' => 'probability * consequence',
                    'probability_values' => [1, 2, 4],
                    'consequence_values' => [1, 2, 4],
                    'severe_consequence' => 4,
                    'annual_review_months' => 12,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $methodologyId = DB::table('prevent_risk_methodologies')
            ->where('code', 'ISP-2025-VEP')
            ->where('version_number', 1)
            ->value('id');

        foreach ($this->catalogs() as $item) {
            DB::table('prevent_risk_catalog_items')->updateOrInsert(
                [
                    'catalog_type' => $item['type'],
                    'code' => $item['code'],
                    'methodology_id' => $methodologyId,
                ],
                [
                    'parent_id' => null,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'color' => $item['color'] ?? null,
                    'sort_order' => $item['sort'] ?? 0,
                    'valid_from' => '2025-01-01',
                    'valid_until' => null,
                    'active' => true,
                    'configuration' => isset($item['config']) ? json_encode($item['config'], JSON_THROW_ON_ERROR) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

    }

    private function installRbac($now): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        foreach ($this->permissions() as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => 'Permiso de Matriz IPER/MIPER y programa preventivo.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $parentId = DB::table('system_modules')->where('slug', 'risk_prevention')->value('id');
        if ($parentId) {
            foreach ($this->navigationModules() as [$slug, $name, $route, $sort]) {
                DB::table('system_modules')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'frontend_route' => $route,
                        'icon' => null,
                        'sort_order' => $sort,
                        'active' => true,
                        'parent_id' => $parentId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $fullPermissionSlugs = array_merge([
            'ver_prevencion_riesgos',
            'gestionar_prevencion_riesgos',
            'exportar_prevencion_riesgos',
            'ver_documentos_prevencion_difundibles',
        ], array_keys($this->permissions()));
        $permissionIds = DB::table('permissions')->whereIn('slug', $fullPermissionSlugs)->pluck('id', 'slug');
        $moduleIds = Schema::hasTable('role_system_module')
            ? DB::table('system_modules')->whereIn('slug', [
                'risk_prevention',
                'risk_prevention_dashboard',
                'risk_prevention_risk_matrices',
                'risk_prevention_risk_imports',
                'risk_prevention_risk_catalogs',
                'risk_prevention_preventive_program',
                'risk_prevention_extinguishers',
                'risk_prevention_accidents',
                'risk_prevention_emergencies',
                'risk_prevention_epp',
                'risk_prevention_trainings',
                'risk_prevention_personnel',
                'risk_prevention_documents',
                'risk_prevention_staff_documents',
                'risk_prevention_reports',
            ])->pluck('id')
            : collect();

        foreach (DB::table('roles')->whereIn('slug', ['super_admin', 'administrador', 'prevencion_riesgos'])->get(['id']) as $role) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            foreach ($moduleIds as $moduleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $role->id,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $readSlugs = ['risk-matrix.view', 'risk-matrix.export', 'preventive-program.view'];
        $riskMatrixModuleIds = Schema::hasTable('role_system_module')
            ? DB::table('system_modules')->whereIn('slug', [
                'risk_prevention',
                'risk_prevention_risk_matrices',
                'risk_prevention_risk_imports',
                'risk_prevention_risk_catalogs',
                'risk_prevention_preventive_program',
            ])->pluck('id')
            : collect();
        foreach (DB::table('roles')->whereIn('slug', ['direccion', 'rrhh', 'inspectoria'])->get(['id']) as $role) {
            foreach ($permissionIds->only($readSlugs) as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            foreach ($riskMatrixModuleIds as $moduleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $role->id,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return [
            'risk-matrix.view' => 'Ver matrices IPER/MIPER',
            'risk-matrix.create' => 'Crear matrices IPER/MIPER',
            'risk-matrix.update' => 'Editar borradores IPER/MIPER',
            'risk-matrix.delete-draft' => 'Eliminar borradores IPER/MIPER',
            'risk-matrix.submit' => 'Enviar matrices IPER/MIPER a revisión',
            'risk-matrix.review' => 'Revisar técnicamente matrices IPER/MIPER',
            'risk-matrix.observe' => 'Observar matrices IPER/MIPER',
            'risk-matrix.approve' => 'Aprobar matrices IPER/MIPER',
            'risk-matrix.archive' => 'Archivar matrices IPER/MIPER',
            'risk-matrix.create-version' => 'Crear versiones IPER/MIPER',
            'risk-matrix.import' => 'Importar matrices IPER/MIPER',
            'risk-matrix.export' => 'Exportar matrices IPER/MIPER',
            'risk-matrix.manage-catalogs' => 'Administrar catálogos IPER/MIPER',
            'risk-matrix.view-audit' => 'Ver auditoría IPER/MIPER',
            'risk-matrix.override-block' => 'Autorizar excepción documentada a bloqueo IPER',
            'risk-control.create' => 'Crear medidas IPER/MIPER',
            'risk-control.update' => 'Editar medidas IPER/MIPER',
            'risk-control.assign' => 'Asignar medidas IPER/MIPER',
            'risk-control.implement' => 'Implementar medidas IPER/MIPER',
            'risk-control.verify' => 'Verificar medidas IPER/MIPER',
            'preventive-program.view' => 'Ver programa preventivo',
            'preventive-program.manage' => 'Gestionar programa preventivo',
        ];
    }

    /** @return array<int, array{string, string, string, int}> */
    private function navigationModules(): array
    {
        return [
            ['risk_prevention_dashboard', 'Dashboard', '/risk-prevention', 1],
            ['risk_prevention_risk_matrices', 'Matrices IPER/MIPER', '/risk-prevention/matrices', 2],
            ['risk_prevention_risk_imports', 'Importaciones IPER', '/risk-prevention/matrices/importaciones', 3],
            ['risk_prevention_risk_catalogs', 'Catálogos y metodología', '/risk-prevention/matrices/catalogos', 4],
            ['risk_prevention_preventive_program', 'Programa de Trabajo Preventivo', '/risk-prevention/preventive-program', 5],
            ['risk_prevention_extinguishers', 'Extintores', '/risk-prevention/extinguishers', 10],
            ['risk_prevention_accidents', 'Accidentes', '/risk-prevention/accidents', 20],
            ['risk_prevention_emergencies', 'Emergencias y planes', '/risk-prevention/emergencies', 30],
            ['risk_prevention_epp', 'EPP y seguridad', '/risk-prevention/epp', 40],
            ['risk_prevention_trainings', 'Capacitaciones', '/risk-prevention/trainings', 50],
            ['risk_prevention_personnel', 'Gestión del personal', '/risk-prevention/personnel', 60],
            ['risk_prevention_documents', 'Gestión documental empresa', '/risk-prevention/documents', 70],
            ['risk_prevention_staff_documents', 'Gestión documental', '/risk-prevention/document-management', 80],
            ['risk_prevention_reports', 'Reportes', '/risk-prevention/reports', 90],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function catalogs(): array
    {
        return [
            ['type' => 'process_type', 'code' => 'operational', 'name' => 'Operacional', 'sort' => 1],
            ['type' => 'process_type', 'code' => 'support', 'name' => 'Apoyo', 'sort' => 2],
            ['type' => 'process_type', 'code' => 'strategic', 'name' => 'Estratégico', 'sort' => 3],
            ['type' => 'process_type', 'code' => 'other', 'name' => 'Otro', 'sort' => 4],
            ['type' => 'task_type', 'code' => 'routine', 'name' => 'Rutinaria', 'sort' => 1],
            ['type' => 'task_type', 'code' => 'non_routine', 'name' => 'No rutinaria', 'sort' => 2],
            ['type' => 'task_type', 'code' => 'occasional', 'name' => 'Ocasional', 'sort' => 3],
            ['type' => 'task_type', 'code' => 'emergency', 'name' => 'Emergencia', 'sort' => 4],
            ['type' => 'exposure_category', 'code' => 'women', 'name' => 'Mujeres', 'sort' => 1],
            ['type' => 'exposure_category', 'code' => 'men', 'name' => 'Hombres', 'sort' => 2],
            ['type' => 'exposure_category', 'code' => 'other_or_unspecified', 'name' => 'Otro / no informado', 'sort' => 3],
            ['type' => 'risk_family', 'code' => 'work_safety', 'name' => 'Seguridad en el trabajo', 'sort' => 1, 'config' => ['default_method' => 'vep', 'residual_allowed' => true, 'requires_document' => false]],
            ['type' => 'risk_family', 'code' => 'emergencies', 'name' => 'Emergencias y desastres', 'sort' => 2, 'config' => ['default_method' => 'vep', 'residual_allowed' => true]],
            ['type' => 'risk_family', 'code' => 'physical_agents', 'name' => 'Agentes físicos', 'sort' => 3, 'config' => ['default_method' => 'protocol', 'residual_allowed' => true, 'requires_document' => true]],
            ['type' => 'risk_family', 'code' => 'chemical_agents', 'name' => 'Agentes químicos', 'sort' => 4, 'config' => ['default_method' => 'quantitative', 'residual_allowed' => true, 'requires_document' => true]],
            ['type' => 'risk_family', 'code' => 'biological_agents', 'name' => 'Agentes biológicos', 'sort' => 5, 'config' => ['default_method' => 'protocol', 'residual_allowed' => true]],
            ['type' => 'risk_family', 'code' => 'ergonomic', 'name' => 'Ergonómicos o musculoesqueléticos', 'sort' => 6, 'config' => ['default_method' => 'protocol', 'residual_allowed' => true]],
            ['type' => 'risk_family', 'code' => 'psychosocial', 'name' => 'Psicosociales', 'sort' => 7, 'config' => ['default_method' => 'protocol', 'residual_allowed' => true, 'requires_document' => true]],
            ['type' => 'risk_family', 'code' => 'organizational', 'name' => 'Organizacionales', 'sort' => 8, 'config' => ['default_method' => 'qualitative', 'residual_allowed' => true]],
            ['type' => 'risk_family', 'code' => 'other', 'name' => 'Otros', 'sort' => 9, 'config' => ['default_method' => 'vep', 'residual_allowed' => true]],
            ['type' => 'evaluation_method', 'code' => 'vep', 'name' => 'VEP: probabilidad x consecuencia', 'sort' => 1],
            ['type' => 'evaluation_method', 'code' => 'protocol', 'name' => 'Protocolo específico', 'sort' => 2],
            ['type' => 'evaluation_method', 'code' => 'quantitative', 'name' => 'Método cuantitativo', 'sort' => 3],
            ['type' => 'evaluation_method', 'code' => 'qualitative', 'name' => 'Método cualitativo', 'sort' => 4],
            ['type' => 'evaluation_method', 'code' => 'external_assessment', 'name' => 'Evaluación externa', 'sort' => 5],
            ['type' => 'probability', 'code' => 'low', 'name' => 'Baja', 'sort' => 1, 'config' => ['score' => 1]],
            ['type' => 'probability', 'code' => 'medium', 'name' => 'Media', 'sort' => 2, 'config' => ['score' => 2]],
            ['type' => 'probability', 'code' => 'high', 'name' => 'Alta', 'sort' => 3, 'config' => ['score' => 4]],
            ['type' => 'consequence', 'code' => 'low', 'name' => 'Ligeramente dañina / baja', 'sort' => 1, 'config' => ['score' => 1]],
            ['type' => 'consequence', 'code' => 'medium', 'name' => 'Dañina / media', 'sort' => 2, 'config' => ['score' => 2]],
            ['type' => 'consequence', 'code' => 'high', 'name' => 'Extremadamente dañina / alta', 'sort' => 3, 'config' => ['score' => 4]],
            ['type' => 'risk_level', 'code' => 'tolerable', 'name' => 'Tolerable', 'color' => '#2e7d32', 'sort' => 1, 'config' => ['scores' => [1, 2], 'recommended_action' => 'Mantener controles y verificar periódicamente.', 'requires_action_plan' => false, 'blocks_approval' => false]],
            ['type' => 'risk_level', 'code' => 'moderate', 'name' => 'Moderado', 'color' => '#d97706', 'sort' => 2, 'config' => ['scores' => [4], 'recommended_action' => 'Planificar medidas de reducción y verificar su eficacia.', 'requires_action_plan' => true, 'blocks_approval' => false]],
            ['type' => 'risk_level', 'code' => 'important', 'name' => 'Importante', 'color' => '#dc6803', 'sort' => 3, 'config' => ['scores' => [8], 'recommended_action' => 'Implementar medidas, responsable y plazo antes de aprobar.', 'requires_action_plan' => true, 'blocks_approval' => false]],
            ['type' => 'risk_level', 'code' => 'intolerable', 'name' => 'Intolerable', 'color' => '#b42318', 'sort' => 4, 'config' => ['scores' => [16], 'recommended_action' => 'Suspender, eliminar o reducir inmediatamente la exposición.', 'requires_action_plan' => true, 'blocks_approval' => true]],
            ['type' => 'control_hierarchy', 'code' => 'elimination', 'name' => 'Eliminación', 'sort' => 1],
            ['type' => 'control_hierarchy', 'code' => 'substitution', 'name' => 'Sustitución', 'sort' => 2],
            ['type' => 'control_hierarchy', 'code' => 'engineering', 'name' => 'Controles de ingeniería', 'sort' => 3],
            ['type' => 'control_hierarchy', 'code' => 'administrative', 'name' => 'Controles administrativos', 'sort' => 4],
            ['type' => 'control_hierarchy', 'code' => 'personal_protective_equipment', 'name' => 'Elementos de protección personal', 'sort' => 5],
            ['type' => 'periodicity', 'code' => 'once', 'name' => 'Una vez', 'sort' => 1],
            ['type' => 'periodicity', 'code' => 'per_event', 'name' => 'Cada vez / por evento', 'sort' => 2],
            ['type' => 'periodicity', 'code' => 'daily', 'name' => 'Diaria', 'sort' => 3],
            ['type' => 'periodicity', 'code' => 'weekly', 'name' => 'Semanal', 'sort' => 4],
            ['type' => 'periodicity', 'code' => 'monthly', 'name' => 'Mensual', 'sort' => 5],
            ['type' => 'periodicity', 'code' => 'quarterly', 'name' => 'Trimestral', 'sort' => 6],
            ['type' => 'periodicity', 'code' => 'semiannual', 'name' => 'Semestral', 'sort' => 7],
            ['type' => 'periodicity', 'code' => 'annual', 'name' => 'Anual', 'sort' => 8],
            ['type' => 'periodicity', 'code' => 'custom', 'name' => 'Personalizada', 'sort' => 9],
            ['type' => 'measure_status', 'code' => 'pending', 'name' => 'Pendiente', 'sort' => 1],
            ['type' => 'measure_status', 'code' => 'planned', 'name' => 'Planificada', 'sort' => 2],
            ['type' => 'measure_status', 'code' => 'in_progress', 'name' => 'En ejecución', 'sort' => 3],
            ['type' => 'measure_status', 'code' => 'implemented', 'name' => 'Implementada', 'sort' => 4],
            ['type' => 'measure_status', 'code' => 'verified', 'name' => 'Verificada', 'sort' => 5],
            ['type' => 'measure_status', 'code' => 'ineffective', 'name' => 'Ineficaz', 'sort' => 6],
            ['type' => 'measure_status', 'code' => 'cancelled', 'name' => 'Cancelada', 'sort' => 7],
            ['type' => 'measure_status', 'code' => 'overdue', 'name' => 'Vencida', 'sort' => 8],
            ['type' => 'review_reason', 'code' => 'annual_review', 'name' => 'Revisión anual', 'sort' => 1],
            ['type' => 'review_reason', 'code' => 'process_change', 'name' => 'Cambio de proceso o tarea', 'sort' => 2],
            ['type' => 'review_reason', 'code' => 'equipment_change', 'name' => 'Cambio de equipo, material o tecnología', 'sort' => 3],
            ['type' => 'review_reason', 'code' => 'accident', 'name' => 'Accidente, incidente o enfermedad ocupacional', 'sort' => 4],
            ['type' => 'review_reason', 'code' => 'regulatory_change', 'name' => 'Cambio normativo', 'sort' => 5],
            ['type' => 'review_reason', 'code' => 'manual_review', 'name' => 'Revisión manual', 'sort' => 6],
            ['type' => 'evidence_type', 'code' => 'photo', 'name' => 'Fotografía', 'sort' => 1],
            ['type' => 'evidence_type', 'code' => 'report', 'name' => 'Informe o medición', 'sort' => 2],
            ['type' => 'evidence_type', 'code' => 'training', 'name' => 'Registro de capacitación', 'sort' => 3],
            ['type' => 'evidence_type', 'code' => 'certificate', 'name' => 'Certificado o acta', 'sort' => 4],
            ['type' => 'evidence_type', 'code' => 'work_order', 'name' => 'Orden de trabajo / respaldo', 'sort' => 5],
            ['type' => 'representation_type', 'code' => 'worker', 'name' => 'Persona trabajadora', 'sort' => 1],
            ['type' => 'representation_type', 'code' => 'management', 'name' => 'Jefatura', 'sort' => 2],
            ['type' => 'representation_type', 'code' => 'joint_committee', 'name' => 'Comité Paritario', 'sort' => 3],
            ['type' => 'representation_type', 'code' => 'safety_delegate', 'name' => 'Delegado de Seguridad y Salud', 'sort' => 4],
            ['type' => 'representation_type', 'code' => 'risk_expert', 'name' => 'Experto en prevención', 'sort' => 5],
            ['type' => 'representation_type', 'code' => 'other', 'name' => 'Otro', 'sort' => 6],
            ['type' => 'protocol', 'code' => 'external_configurable', 'name' => 'Protocolo o método específico configurable', 'sort' => 1, 'config' => ['requires_document' => true]],
        ];
    }
}
