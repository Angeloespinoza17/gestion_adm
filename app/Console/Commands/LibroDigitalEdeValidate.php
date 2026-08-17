<?php

namespace App\Console\Commands;

use App\Jobs\ValidateLibroDigitalEdeExport;
use App\Models\LibroDigital\EdeExport;
use App\Models\User;
use Illuminate\Console\Command;

class LibroDigitalEdeValidate extends Command
{
    protected $signature = 'lcd:ede:validate
        {--export= : ID público o interno de la exportación}
        {--actor= : ID del usuario autorizado}
        {--execute : Encola la validación oficial}
        {--approval-reference= : Referencia de aprobación operativa}
        {--json}';

    protected $description = 'Prepara o encola check EDE aislado; nunca ejecuta el contenedor dentro de la petición web.';

    public function handle(): int
    {
        $identifier = trim((string) $this->option('export'));
        $export = EdeExport::query()->where(function ($query) use ($identifier): void {
            if (ctype_digit($identifier)) {
                $query->whereKey((int) $identifier)->orWhere('public_id', $identifier);
            } else {
                $query->where('public_id', $identifier);
            }
        })->first();
        if (! $export) {
            $this->error('La exportación indicada no existe.');

            return self::INVALID;
        }

        $status = $export->status->value;
        $ready = in_array($status, ['generated', 'validation_failed'], true);
        $payload = [
            'export' => $export->public_id,
            'status' => $status,
            'ready_to_enqueue' => $ready,
            'validator_status' => $export->validator_status,
            'source_snapshot_hash' => $export->source_snapshot_hash,
        ];
        if (! $this->option('execute')) {
            $payload['dry_run'] = true;
            $this->line($this->option('json')
                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : ($ready ? '<info>La exportación puede encolarse con --execute.</info>' : '<error>La exportación no está en un estado validable.</error>'));

            return $ready ? self::SUCCESS : self::FAILURE;
        }

        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor || ! $actor->hasPermission('libro_digital.ede.validate')) {
            $this->error('Debes indicar un actor con permiso libro_digital.ede.validate.');

            return self::INVALID;
        }
        if (! $ready || blank($this->option('approval-reference'))) {
            $this->error('La exportación debe estar generada y se requiere --approval-reference.');

            return self::FAILURE;
        }

        ValidateLibroDigitalEdeExport::dispatch($export->id, $actor->id);
        $payload['queued'] = true;
        $payload['approval_reference_hash'] = hash('sha256', (string) $this->option('approval-reference'));
        $this->line($this->option('json')
            ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '<info>Validación EDE encolada; revisa el run y el reporte contractual antes de liberar.</info>');

        return self::SUCCESS;
    }
}
