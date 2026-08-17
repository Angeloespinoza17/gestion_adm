<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\EdeMapping;
use App\Models\LibroDigital\EdeVersion;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class EdeStandardImportService
{
    private const MAX_ARTIFACT_BYTES = 20 * 1024 * 1024;

    /** @var list<string> */
    private const SOURCE_ENTITIES = [
        'book', 'enrollment', 'session', 'session_attendance', 'assessment',
        'assessment_result', 'coexistence', 'pie_support', 'withdrawal',
    ];

    /** @var list<string> */
    private const TRANSFORMS = ['direct', 'string', 'integer', 'boolean', 'date', 'enum_map'];

    public function __construct(private readonly CanonicalJson $canonical) {}

    /**
     * @param array{
     *   version:string, code?:string, source_path:string, schema_path:string, mappings_path:string,
     *   authority?:string|null, source_url?:string|null, effective_from?:string|null,
     *   activate?:bool, approval_reference?:string|null
     * } $input
     * @return array{version:EdeVersion,created:bool,source_hash:string,schema_hash:string,mappings_hash:string,mapping_count:int,archive_path:string}
     */
    public function import(array $input, ?User $actor = null): array
    {
        $version = trim($input['version'] ?? '');
        $code = Str::upper(trim($input['code'] ?? 'CEDS'));
        if ($version === '' || $code === '') {
            throw new LibroDigitalException('La versión y el código del estándar son obligatorios.', 'LCD_EDE_STANDARD_IDENTITY_REQUIRED', 422);
        }
        if (($input['activate'] ?? false) && blank($input['approval_reference'] ?? null)) {
            throw new LibroDigitalException('Activar una versión exige una referencia de aprobación.', 'LCD_EDE_STANDARD_APPROVAL_REQUIRED', 422);
        }

        $source = $this->artifact($input['source_path'] ?? '', 'fuente');
        $schema = $this->artifact($input['schema_path'] ?? '', 'esquema');
        $mappingsArtifact = $this->artifact($input['mappings_path'] ?? '', 'mapeos');
        try {
            $mappings = json_decode($mappingsArtifact['contents'], true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LibroDigitalException('El archivo de mapeos no contiene JSON válido.', 'LCD_EDE_MAPPINGS_JSON_INVALID', 422, ['json_error' => $exception->getMessage()]);
        }
        $this->validateMappings($mappings);

        $sourceHash = hash('sha256', $source['contents']);
        $schemaHash = hash('sha256', $schema['contents']);
        $mappingsHash = hash('sha256', $mappingsArtifact['contents']);
        $existing = EdeVersion::query()->where('code', $code)->where('version', $version)->first();
        if ($existing) {
            $storedMappingsHash = $existing->metadata['mappings_hash'] ?? null;
            if (! hash_equals((string) $existing->source_hash, $sourceHash)
                || ! hash_equals((string) $existing->schema_hash, $schemaHash)
                || ! is_string($storedMappingsHash)
                || ! hash_equals($storedMappingsHash, $mappingsHash)) {
                throw new LibroDigitalException(
                    'La versión ya existe con artefactos distintos. Debes importar un número de versión nuevo; no se sobrescribió información.',
                    'LCD_EDE_STANDARD_VERSION_CONFLICT',
                    409,
                );
            }

            return [
                'version' => $existing, 'created' => false,
                'source_hash' => $sourceHash, 'schema_hash' => $schemaHash,
                'mappings_hash' => $mappingsHash,
                'mapping_count' => $existing->mappings()->count(),
                'archive_path' => (string) ($existing->metadata['archive_path'] ?? ''),
            ];
        }

        // El sufijo de contenido evita que dos importaciones concurrentes con
        // artefactos distintos sobrescriban el archivo privado de la ganadora.
        $archivePath = 'libro-digital/ede/'.$this->safeSegment(
            $code.'-'.$version.'-'.substr(hash('sha256', $sourceHash.$schemaHash.$mappingsHash), 0, 18),
        );
        $this->archive($archivePath, $source, $schema, $mappingsArtifact, [
            'code' => $code,
            'version' => $version,
            'source' => ['bytes' => $source['bytes'], 'sha256' => $sourceHash, 'mime' => $source['mime']],
            'schema' => ['bytes' => $schema['bytes'], 'sha256' => $schemaHash, 'mime' => $schema['mime']],
            'mappings' => ['bytes' => $mappingsArtifact['bytes'], 'sha256' => $mappingsHash, 'mime' => $mappingsArtifact['mime']],
            'archived_at' => now('UTC')->toIso8601String(),
        ]);

        $record = DB::transaction(function () use ($input, $actor, $archivePath, $code, $version, $source, $schema, $mappingsArtifact, $sourceHash, $schemaHash, $mappingsHash, $mappings): EdeVersion {
            $concurrent = EdeVersion::query()->where('code', $code)->where('version', $version)->lockForUpdate()->first();
            if ($concurrent) {
                throw new LibroDigitalException('La versión fue importada concurrentemente; vuelve a consultar el catálogo.', 'LCD_EDE_STANDARD_IMPORT_CONFLICT', 409);
            }
            $edeVersion = EdeVersion::query()->create([
                'code' => $code,
                'version' => $version,
                'authority' => filled($input['authority'] ?? null) ? trim((string) $input['authority']) : null,
                'source_url' => filled($input['source_url'] ?? null) ? trim((string) $input['source_url']) : null,
                'source_hash' => $sourceHash,
                'schema_hash' => $schemaHash,
                'effective_from' => $input['effective_from'] ?? null,
                'status' => ($input['activate'] ?? false) ? 'active' : 'imported',
                'metadata' => [
                    'approval_reference' => $input['approval_reference'] ?? null,
                    'archive_path' => $archivePath,
                    'source_bytes' => $source['bytes'],
                    'schema_bytes' => $schema['bytes'],
                    'mappings_bytes' => $mappingsArtifact['bytes'],
                    'mappings_hash' => $mappingsHash,
                    'artifacts_encrypted' => true,
                ],
                'imported_at' => now('UTC'),
                'imported_by' => $actor?->id,
            ]);

            foreach ($mappings as $mapping) {
                $definition = [
                    'source_entity' => $mapping['source_entity'],
                    'source_field' => $mapping['source_field'] ?? null,
                    'target_record_type' => $mapping['target_record_type'],
                    'target_field' => $mapping['target_field'],
                    'data_type' => $mapping['data_type'],
                    'required' => (bool) ($mapping['required'] ?? false),
                    'transform_definition' => $mapping['transform_definition'] ?? ['type' => 'direct'],
                    'validation_definition' => $mapping['validation_definition'] ?? null,
                    'default_value' => $mapping['default_value'] ?? null,
                ];
                EdeMapping::query()->create([
                    'ede_version_id' => $edeVersion->id,
                    'regulatory_profile_id' => $mapping['regulatory_profile_id'] ?? null,
                    'code' => $mapping['code'],
                    ...$definition,
                    'mapping_hash' => $this->canonical->hash($definition),
                    'effective_from' => $mapping['effective_from'] ?? ($input['effective_from'] ?? null),
                    'effective_to' => $mapping['effective_to'] ?? null,
                    'active' => (bool) ($mapping['active'] ?? true),
                ]);
            }

            return $edeVersion;
        }, 3);

        return [
            'version' => $record, 'created' => true,
            'source_hash' => $sourceHash, 'schema_hash' => $schemaHash,
            'mappings_hash' => $mappingsHash, 'mapping_count' => count($mappings),
            'archive_path' => $archivePath,
        ];
    }

    /** @return array{contents:string,bytes:int,mime:string} */
    private function artifact(string $path, string $label): array
    {
        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new LibroDigitalException("No se pudo leer el artefacto de {$label}.", 'LCD_EDE_STANDARD_FILE_INVALID', 422, ['artifact' => $label]);
        }
        $bytes = filesize($real);
        if (! is_int($bytes) || $bytes < 1 || $bytes > self::MAX_ARTIFACT_BYTES) {
            throw new LibroDigitalException("El artefacto de {$label} supera el tamaño permitido o está vacío.", 'LCD_EDE_STANDARD_FILE_SIZE_INVALID', 422, ['artifact' => $label]);
        }
        $contents = file_get_contents($real);
        if (! is_string($contents)) {
            throw new LibroDigitalException("No se pudo leer el artefacto de {$label}.", 'LCD_EDE_STANDARD_FILE_INVALID', 422, ['artifact' => $label]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        return ['contents' => $contents, 'bytes' => $bytes, 'mime' => $mime];
    }

    private function validateMappings(mixed $mappings): void
    {
        if (! is_array($mappings) || ! array_is_list($mappings) || $mappings === []) {
            throw new LibroDigitalException('El archivo debe contener una lista no vacía de mapeos declarativos.', 'LCD_EDE_MAPPINGS_EMPTY', 422);
        }
        if (count($mappings) > 10000) {
            throw new LibroDigitalException('El archivo excede el máximo de mapeos permitido.', 'LCD_EDE_MAPPINGS_LIMIT_EXCEEDED', 422);
        }
        $codes = [];
        foreach ($mappings as $index => $mapping) {
            if (! is_array($mapping)) {
                throw new LibroDigitalException("Mapeo {$index}: debe ser un objeto.", 'LCD_EDE_MAPPING_INVALID', 422);
            }
            foreach (['code', 'source_entity', 'target_record_type', 'target_field', 'data_type'] as $field) {
                if (! filled($mapping[$field] ?? null) || mb_strlen((string) $mapping[$field]) > 120) {
                    throw new LibroDigitalException("Mapeo {$index}: {$field} es obligatorio y debe ser acotado.", 'LCD_EDE_MAPPING_FIELD_INVALID', 422);
                }
            }
            if (! in_array($mapping['source_entity'], self::SOURCE_ENTITIES, true)) {
                throw new LibroDigitalException("Mapeo {$index}: source_entity no permitido.", 'LCD_EDE_MAPPING_SOURCE_INVALID', 422);
            }
            $transform = $mapping['transform_definition']['type'] ?? 'direct';
            if (! in_array($transform, self::TRANSFORMS, true)) {
                throw new LibroDigitalException("Mapeo {$index}: transformación no permitida.", 'LCD_EDE_MAPPING_TRANSFORM_INVALID', 422);
            }
            $code = (string) $mapping['code'];
            if (isset($codes[$code])) {
                throw new LibroDigitalException("Mapeo {$index}: código duplicado.", 'LCD_EDE_MAPPING_CODE_DUPLICATED', 422);
            }
            $codes[$code] = true;
            if (isset($mapping['regulatory_profile_id']) && ! RegulatoryProfile::query()->whereKey($mapping['regulatory_profile_id'])->exists()) {
                throw new LibroDigitalException("Mapeo {$index}: perfil normativo inexistente.", 'LCD_EDE_MAPPING_PROFILE_INVALID', 422);
            }
        }
    }

    /** @param array<string,mixed> $checksums */
    private function archive(string $base, array $source, array $schema, array $mappings, array $checksums): void
    {
        $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
        $files = [
            $base.'/source/source.bin.enc' => Crypt::encryptString($source['contents']),
            $base.'/source/schema.bin.enc' => Crypt::encryptString($schema['contents']),
            $base.'/normalized/mappings.json.enc' => Crypt::encryptString($mappings['contents']),
            $base.'/checksums.json.enc' => Crypt::encryptString($this->canonical->encode($checksums)),
        ];
        foreach ($files as $path => $contents) {
            if (! $disk->put($path, $contents)) {
                throw new LibroDigitalException('No fue posible archivar los artefactos EDE en almacenamiento privado.', 'LCD_EDE_STANDARD_ARCHIVE_FAILED', 500);
            }
        }
    }

    private function safeSegment(string $value): string
    {
        $segment = Str::slug($value);

        return $segment !== '' ? $segment : 'standard-'.substr(hash('sha256', $value), 0, 16);
    }
}
