<?php

namespace App\Console\Commands;

use App\Services\LibroDigital\LibroDigitalIntegrityService;
use Illuminate\Console\Command;

class LibroDigitalIntegrityCheck extends Command
{
    protected $signature = 'lcd:integrity:check {--school= : ID interno del establecimiento} {--json : Salida JSON}';

    protected $description = 'Audita firmas, snapshots, archivos, EDE y cadena hash; nunca repara ni elimina.';

    public function handle(LibroDigitalIntegrityService $service): int
    {
        $result = $service->check($this->option('school') ? (int) $this->option('school') : null);
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Área', 'Registros verificados'], collect($result['checked'])->map(fn ($count, $area) => [$area, $count])->all());
            $this->line($result['valid'] ? '<info>Integridad verificada.</info>' : '<error>Se encontraron '.count($result['issues']).' incidencias. No se realizó reparación automática.</error>');
        }

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
