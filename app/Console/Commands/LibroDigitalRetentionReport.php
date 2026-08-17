<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibroDigitalRetentionReport extends Command
{
    protected $signature = 'lcd:retention:report {--school= : ID interno del establecimiento} {--json : Salida JSON}';

    protected $description = 'Informa vencimientos de retención; no borra, anonimiza ni modifica datos.';

    public function handle(): int
    {
        if (! Schema::hasTable('lcd_books')) {
            $this->error('El esquema LCD no está instalado.');

            return self::FAILURE;
        }

        $rows = DB::table('lcd_books as b')->join('lcd_schools as s', 's.id', '=', 'b.school_id')
            ->when($this->option('school'), fn ($query, $id) => $query->where('b.school_id', (int) $id))
            ->whereNotNull('b.retention_until')->whereDate('b.retention_until', '<=', now()->toDateString())
            ->orderBy('b.retention_until')->get(['b.public_id', 's.rbd', 'b.code', 'b.status', 'b.retention_until']);
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'informational_only' => true,
            'automatic_deletion_performed' => false,
            'requires_legal_review_and_hold_check' => true,
            'records' => $rows,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['ID', 'RBD', 'Libro', 'Estado', 'Retención hasta'], $rows->map(fn ($row) => (array) $row)->all());
            $this->warn('Informe solamente: cualquier disposición requiere revisión jurídica, legal hold y evidencia separada.');
        }

        return self::SUCCESS;
    }
}
