<?php

namespace App\Console\Commands;

use App\Services\Rbac\RbacReconciliationService;
use Illuminate\Console\Command;

class AuditRbac extends Command
{
    protected $signature = 'rbac:audit
        {--strict : Retornar error si existe cualquier inconsistencia crítica}
        {--json : Escribir el resultado completo en JSON}';

    protected $description = 'Audita permisos, grupos, módulos y perfiles de roles sin modificar datos';

    public function handle(RbacReconciliationService $service): int
    {
        $audit = $service->audit();

        if ($this->option('json')) {
            $this->line(json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Roles', 'Permisos', 'Módulos', 'Grupos', 'Problemas críticos'],
                [[
                    $audit['counts']['roles'],
                    $audit['counts']['permissions'],
                    $audit['counts']['modules'],
                    $audit['counts']['permission_groups'],
                    $audit['critical_issue_count'],
                ]],
            );

            foreach ($this->issueLabels() as $key => $label) {
                $issues = $audit[$key];

                if (! empty($issues)) {
                    $this->warn($label.': '.json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }
        }

        if ($audit['critical_issue_count'] === 0) {
            $this->info('Auditoría RBAC correcta: no se detectaron inconsistencias críticas.');

            return self::SUCCESS;
        }

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, string> */
    private function issueLabels(): array
    {
        return [
            'missing_backend_permissions' => 'Permisos faltantes en rutas API',
            'missing_frontend_permissions' => 'Permisos faltantes en rutas Vue',
            'ungrouped_permissions' => 'Permisos sin grupo',
            'groups_without_active_module' => 'Grupos sin módulo activo',
            'contaminated_roles' => 'Roles con permisos globales incorrectos de Inventario',
            'nurse_missing_permissions' => 'Permisos faltantes de Enfermería',
            'nurse_missing_modules' => 'Módulos faltantes de Enfermería',
            'invalid_role_slugs' => 'Slugs de rol no normalizados',
            'roles_with_users_without_permissions' => 'Roles con usuarios y sin permisos',
        ];
    }
}
