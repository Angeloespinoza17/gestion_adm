<?php

namespace App\Console\Commands;

use App\Services\LibroDigital\LibroDigitalHealthService;
use Illuminate\Console\Command;

class LibroDigitalHealth extends Command
{
    protected $signature = 'lcd:health {--json : Salida JSON sin detalles sensibles}';

    protected $description = 'Comprueba dependencias técnicas del LCD sin exponer secretos ni modificar datos oficiales.';

    public function handle(LibroDigitalHealthService $service): int
    {
        $result = $service->check();
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Control', 'Estado', 'Requerido', 'ms'], collect($result['checks'])->map(fn ($check) => [$check['code'], $check['passed'] ? 'OK' : 'NO DISPONIBLE', $check['required'] ? 'Sí' : 'No', $check['duration_ms'] ?? '-'])->all());
        }

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
