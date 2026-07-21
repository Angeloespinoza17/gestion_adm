<?php

namespace App\Console\Commands;

use App\Services\Rbac\RbacReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileRbac extends Command
{
    protected $signature = 'rbac:reconcile
        {--apply : Aplicar el cambio; sin esta opción solo presenta el dry-run}
        {--json : Escribir el diff completo en JSON}';

    protected $description = 'Reconcilia el catálogo RBAC y los perfiles base sin eliminar datos operacionales';

    public function handle(RbacReconciliationService $service): int
    {
        $apply = (bool) $this->option('apply');

        try {
            $result = $apply ? $service->apply() : $service->preview();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('La reconciliación RBAC falló y fue revertida: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info($apply ? 'Reconciliación RBAC aplicada.' : 'Dry-run RBAC completado; no se modificó la base de datos.');
            $this->table(
                ['Permisos nuevos', 'Módulos nuevos', 'Grupos nuevos', 'Enlaces de grupo +', 'Enlaces de grupo -', 'Roles modificados'],
                [[
                    count($result['permissions_created']),
                    count($result['modules_created']),
                    count($result['groups_created']),
                    $result['group_links_added'],
                    $result['group_links_removed'],
                    count($result['role_changes']),
                ]],
            );

            foreach ($result['role_changes'] as $change) {
                $slug = $change['slug_before'] === $change['slug_after']
                    ? $change['slug_after']
                    : $change['slug_before'].' -> '.$change['slug_after'];
                $this->line(sprintf(
                    '%s: permisos +%d/-%d, módulos +%d/-%d',
                    $slug,
                    count($change['permissions_added']),
                    count($change['permissions_removed']),
                    count($change['modules_added']),
                    count($change['modules_removed']),
                ));
            }
        }

        if ($result['audit']['critical_issue_count'] > 0) {
            $this->error('El resultado conserva inconsistencias críticas; no debe desplegarse.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
