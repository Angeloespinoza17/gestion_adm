<?php

namespace App\Console\Commands;

use App\Services\LibroDigital\CompliancePreflightService;
use Illuminate\Console\Command;

class LibroDigitalPreflight extends Command
{
    protected $signature = 'lcd:preflight {--school= : ID interno del establecimiento} {--json : Salida JSON}';

    protected $description = 'Ejecuta controles no destructivos previos a habilitar el Libro Digital.';

    public function handle(CompliancePreflightService $service): int
    {
        $result = $service->run($this->option('school') ? (int) $this->option('school') : null);
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Control', 'Capacidad', 'Resultado', 'Remediación'], collect($result['checks'])->map(fn ($check) => [
                $check['label'],
                $check['capability'] ?? 'core',
                match ($check['status'] ?? null) {
                    'passed', 'available' => 'OK',
                    'skipped' => 'OMITIDO (flag apagado)',
                    default => 'BLOQUEADO',
                },
                ($check['configured'] ?? false) || ! ($check['required'] ?? true) ? '-' : $check['remediation'],
            ])->all());
            $this->line($result['ready'] ? '<info>Preflight conforme.</info>' : '<error>Preflight bloqueado; no habilitar producción.</error>');
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
