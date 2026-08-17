<?php

namespace App\Console\Commands;

use App\Services\LibroDigital\LibroDigitalIntegrityService;
use Illuminate\Console\Command;

class LibroDigitalAttendanceIntegrity extends Command
{
    protected $signature = 'lcd:attendance:check-integrity {--school= : ID interno del establecimiento} {--json : Salida JSON}';

    protected $description = 'Verifica snapshots, firmas y registros que sustentan la asistencia, sin reparar ni reescribir historia.';

    public function handle(LibroDigitalIntegrityService $integrity): int
    {
        $result = $integrity->check($this->option('school') ? (int) $this->option('school') : null);
        $areas = ['roster', 'signature'];
        $issues = collect($result['issues'])->whereIn('area', $areas)->values()->all();
        $payload = [
            'valid' => $issues === [],
            'checked' => [
                'rosters' => $result['checked']['rosters'] ?? 0,
                'signed_sessions' => $result['checked']['signed_sessions'] ?? 0,
            ],
            'issues' => $issues,
            'repair_performed' => false,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Área', 'Revisados'], collect($payload['checked'])->map(fn ($count, $area) => [$area, $count])->all());
            $this->line($payload['valid']
                ? '<info>Integridad de asistencia verificada.</info>'
                : '<error>Se encontraron '.count($issues).' incidencias; no se reparó automáticamente.</error>');
        }

        return $payload['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
