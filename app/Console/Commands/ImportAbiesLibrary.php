<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Library\AbiesImportService;
use App\Services\Library\AbiesMdbReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;
use ZipArchive;

class ImportAbiesLibrary extends Command
{
    protected $signature = 'library:import-abies
        {source : Ruta al archivo Abies .zip o .mdb}
        {--apply : Aplicar la importación; sin esta opción solo se analiza}
        {--skip-images : No convertir ni importar portadas BMP}
        {--actor-email= : Usuario responsable que quedará en la auditoría}
        {--force : Permitir la ejecución fuera del entorno local}';

    protected $description = 'Importa obras, ejemplares y lectores sin usuario desde una base Abies 2.0.';

    public function handle(AbiesImportService $service): int
    {
        $temporaryDirectory = null;

        try {
            $sourcePath = $this->resolvePath((string) $this->argument('source'));
            [$databasePath, $temporaryDirectory] = $this->resolveDatabase($sourcePath);
            $reader = new AbiesMdbReader($databasePath);
            $preview = $service->preview($reader);

            $this->components->info('Base Abies validada: '.basename($sourcePath));
            $this->line('SHA-256: '.hash_file('sha256', $sourcePath));
            $this->renderPreview($preview);

            if (! $this->option('apply')) {
                $this->components->info('Simulación finalizada. No se modificó la base local.');
                $this->line('Usa --apply para ejecutar la importación.');

                return self::SUCCESS;
            }

            if (! app()->environment('local') && ! $this->option('force')) {
                throw new RuntimeException('La importación solo está habilitada en local. Usa --force para autorizar otro entorno.');
            }

            $actorId = $this->actorId();
            $currentPhase = null;
            $progressBar = null;
            $progress = function (string $phase, int $current, int $total) use (&$currentPhase, &$progressBar): void {
                if ($phase !== $currentPhase) {
                    if ($progressBar instanceof ProgressBar) {
                        $progressBar->finish();
                        $this->newLine(2);
                    }
                    $currentPhase = $phase;
                    $this->line('Importando '.$phase.'...');
                    $progressBar = $this->output->createProgressBar(max(1, $total));
                    $progressBar->start();
                }
                $progressBar?->setProgress(min($current, max(1, $total)));
            };

            $summary = $service->import(
                $reader,
                $actorId,
                ! $this->option('skip-images'),
                $progress,
            );

            if ($progressBar instanceof ProgressBar) {
                $progressBar->finish();
                $this->newLine(2);
            }

            $this->components->info('Importación Abies completada.');
            $this->table(['Resultado', 'Cantidad'], collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($temporaryDirectory && is_dir($temporaryDirectory)) {
                File::deleteDirectory($temporaryDirectory);
            }
        }
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Debes indicar el archivo Abies.');
        }

        if (Str::startsWith($path, '~/')) {
            $home = getenv('HOME');
            $path = $home ? $home.substr($path, 1) : $path;
        }
        if (! Str::startsWith($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new RuntimeException("No se pudo leer el archivo {$path}.");
        }

        return $resolved;
    }

    /** @return array{0:string,1:?string} */
    private function resolveDatabase(string $sourcePath): array
    {
        if (Str::lower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'mdb') {
            return [$sourcePath, null];
        }
        if (Str::lower(pathinfo($sourcePath, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('El origen debe ser un archivo .zip o .mdb de Abies.');
        }

        $zip = new ZipArchive;
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP de Abies.');
        }

        $entry = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && Str::lower(pathinfo($name, PATHINFO_EXTENSION)) === 'mdb') {
                $entry = $name;
                break;
            }
        }
        if (! $entry) {
            $zip->close();
            throw new RuntimeException('El ZIP no contiene una base .mdb.');
        }

        $temporaryDirectory = sys_get_temp_dir().'/abies-import-'.Str::random(12);
        File::makeDirectory($temporaryDirectory, 0700, true);
        $target = $temporaryDirectory.'/abies.mdb';
        $input = $zip->getStream($entry);
        $output = fopen($target, 'wb');
        if ($input === false || $output === false) {
            $zip->close();
            File::deleteDirectory($temporaryDirectory);
            throw new RuntimeException('No se pudo extraer la base .mdb del ZIP.');
        }

        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('Falló la extracción de la base .mdb.');
            }
        } finally {
            fclose($input);
            fclose($output);
            $zip->close();
        }

        return [$target, $temporaryDirectory];
    }

    private function actorId(): ?int
    {
        $email = trim((string) $this->option('actor-email'));
        if ($email !== '') {
            return User::query()->where('email', $email)->value('id')
                ?? throw new RuntimeException("No existe un usuario con correo {$email}.");
        }

        return User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->value('id');
    }

    /** @param array<string, mixed> $preview */
    private function renderPreview(array $preview): void
    {
        $this->table([
            'Obras',
            'Ejemplares',
            'Lectores',
            'Coinciden con padrón',
            'Temporales',
            'Préstamos activos',
        ], [[
            $preview['works'],
            $preview['copies'],
            $preview['readers'],
            $preview['readers_matching_current_people'],
            $preview['temporary_readers_to_create'],
            $preview['active_legacy_loans'],
        ]]);

        $this->table(['Control', 'Cantidad'], [
            ['Colisiones código de obra', $preview['work_code_collisions']],
            ['Colisiones código externo de obra', $preview['work_barcode_collisions']],
            ['Colisiones código de ejemplar', $preview['copy_code_collisions']],
            ['Obras Abies ya importadas', $preview['existing_imported_works']],
            ['Ejemplares Abies ya importados', $preview['existing_imported_copies']],
            ['Lectores temporales Abies ya importados', $preview['existing_imported_temporary_readers']],
        ]);
    }
}
