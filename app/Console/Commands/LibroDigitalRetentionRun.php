<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibroDigitalRetentionRun extends Command
{
    protected $signature = 'lcd:retention:run
        {--school= : ID interno del establecimiento}
        {--dry-run : Genera solamente el plan de revisión}
        {--execute : Solicita ejecución de disposición}
        {--approval-reference= : Referencia de aprobación jurídica y de privacidad}
        {--json : Salida JSON}';

    protected $description = 'Evalúa retención sin borrar datos; la disposición permanece bloqueada hasta implementar legal holds y aprobación por registro.';

    public function handle(): int
    {
        if (! Schema::hasTable('lcd_books')) {
            $this->error('El esquema LCD no está instalado.');

            return self::FAILURE;
        }

        if ($this->option('execute')) {
            $payload = [
                'code' => 'COMPLIANCE_BLOCKER_RETENTION_DISPOSAL',
                'message' => 'La eliminación o anonimización automática no está autorizada: faltan legal holds, aprobación por registro y evidencia de disposición.',
                'automatic_deletion_performed' => false,
                'approval_reference_received' => filled($this->option('approval-reference')),
            ];
            $this->line($this->option('json')
                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : '<error>'.$payload['message'].'</error>');

            return self::FAILURE;
        }

        $rows = DB::table('lcd_books as b')
            ->join('lcd_schools as s', 's.id', '=', 'b.school_id')
            ->when($this->option('school'), fn ($query, $id) => $query->where('b.school_id', (int) $id))
            ->whereNotNull('b.retention_until')
            ->whereDate('b.retention_until', '<=', now()->toDateString())
            ->orderBy('b.retention_until')
            ->get(['b.public_id', 's.rbd', 'b.code', 'b.status', 'b.retention_until']);
        $payload = [
            'dry_run' => true,
            'automatic_deletion_performed' => false,
            'requires_legal_hold_check' => true,
            'requires_record_level_approval' => true,
            'candidate_count' => $rows->count(),
            'records' => $rows,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['ID', 'RBD', 'Libro', 'Estado', 'Retención hasta'], $rows->map(fn ($row) => (array) $row)->all());
            $this->warn('DRY RUN: no se borró, anonimizó ni modificó ningún registro.');
        }

        return self::SUCCESS;
    }
}
