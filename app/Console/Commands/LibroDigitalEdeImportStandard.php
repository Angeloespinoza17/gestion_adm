<?php

namespace App\Console\Commands;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\User;
use App\Services\LibroDigital\EdeStandardImportService;
use Illuminate\Console\Command;
use Throwable;

class LibroDigitalEdeImportStandard extends Command
{
    protected $signature = 'lcd:ede:import-standard
        {--version= : Versión exacta del artefacto}
        {--code=CEDS : Código del estándar}
        {--source= : Archivo oficial descargado}
        {--schema= : Archivo de esquema/diccionario oficial}
        {--mappings= : JSON declarativo de mapeos revisados}
        {--authority=MINEDUC : Organismo emisor}
        {--url= : URL oficial de origen}
        {--effective-from= : Vigencia Y-m-d}
        {--actor= : ID del usuario responsable}
        {--activate : Activa la versión importada}
        {--approval-reference= : Evidencia de revisión para activar}
        {--json : Salida JSON}';

    protected $description = 'Importa, cifra, archiva y hashea artefactos EDE locales sin inventar referencias ni descargar fuentes implícitamente.';

    public function handle(EdeStandardImportService $importer): int
    {
        $version = trim((string) $this->option('version'));
        $source = $this->realFile((string) $this->option('source'));
        $schema = $this->realFile((string) $this->option('schema'));
        $mappings = $this->realFile((string) $this->option('mappings'));
        if ($version === '' || ! $source || ! $schema || ! $mappings) {
            $this->error('Debes indicar --version y archivos existentes --source, --schema y --mappings.');

            return self::INVALID;
        }
        if ($this->option('activate') && blank($this->option('approval-reference'))) {
            $this->error('Activar una versión requiere --approval-reference.');

            return self::INVALID;
        }
        $actor = null;
        if ($this->option('actor')) {
            $actor = User::query()->find((int) $this->option('actor'));
            if (! $actor) {
                $this->error('El actor indicado no existe.');

                return self::INVALID;
            }
        }

        try {
            $result = $importer->import([
                'version' => $version,
                'code' => (string) $this->option('code'),
                'source_path' => $source,
                'schema_path' => $schema,
                'mappings_path' => $mappings,
                'authority' => (string) $this->option('authority'),
                'source_url' => filled($this->option('url')) ? (string) $this->option('url') : null,
                'effective_from' => filled($this->option('effective-from')) ? (string) $this->option('effective-from') : null,
                'activate' => (bool) $this->option('activate'),
                'approval_reference' => filled($this->option('approval-reference')) ? (string) $this->option('approval-reference') : null,
            ], $actor);
        } catch (LibroDigitalException $exception) {
            $this->error($exception->errorCode.': '.$exception->getMessage());

            return $exception->status >= 500 ? self::FAILURE : self::INVALID;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('La importación falló de forma segura; no se activó ni reemplazó ninguna versión.');

            return self::FAILURE;
        }

        $edeVersion = $result['version'];
        $payload = [
            'public_id' => $edeVersion->public_id,
            'code' => $edeVersion->code,
            'version' => $edeVersion->version,
            'status' => $edeVersion->status,
            'created' => $result['created'],
            'source_sha256' => $result['source_hash'],
            'schema_sha256' => $result['schema_hash'],
            'mappings_sha256' => $result['mappings_hash'],
            'mapping_count' => $result['mapping_count'],
            'artifacts_archived_privately' => true,
        ];
        $this->line($this->option('json')
            ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : sprintf('EDE %s %s %s (%d mapeos), estado %s.', $edeVersion->code, $edeVersion->version, $result['created'] ? 'importado' : 'ya existente', $result['mapping_count'], $edeVersion->status));

        return self::SUCCESS;
    }

    private function realFile(string $path): ?string
    {
        if (trim($path) === '') {
            return null;
        }
        $resolved = realpath($path);

        return $resolved !== false && is_file($resolved) && is_readable($resolved) ? $resolved : null;
    }
}
