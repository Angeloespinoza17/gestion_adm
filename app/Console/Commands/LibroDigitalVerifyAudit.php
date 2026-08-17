<?php

namespace App\Console\Commands;

use App\Services\LibroDigital\AuditIntegrityVerifier;
use Illuminate\Console\Command;

class LibroDigitalVerifyAudit extends Command
{
    protected $signature = 'lcd:audit:verify {--school= : ID interno del establecimiento} {--json : Salida JSON}';

    protected $description = 'Verifica la cadena hash append-only de auditoría LCD sin modificar registros.';

    public function handle(AuditIntegrityVerifier $service): int
    {
        $result = $service->verify($this->option('school') ? (int) $this->option('school') : null);
        $this->line($this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : sprintf('%s · eventos: %d · errores: %d', $result['valid'] ? 'CADENA VÁLIDA' : 'CADENA INVÁLIDA', $result['checked'], count($result['errors'])));

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
