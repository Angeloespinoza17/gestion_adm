<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Exceptions\LibroDigital\VersionConflictException;
use App\Http\Requests\LibroDigital\ImportEdeStandardRequest;
use App\Http\Requests\LibroDigital\ListEdeExportsRequest;
use App\Http\Requests\LibroDigital\ListEdeMappingsRequest;
use App\Http\Requests\LibroDigital\StoreEdeExportRequest;
use App\Http\Requests\LibroDigital\ValidateEdeExportRequest;
use App\Jobs\ValidateLibroDigitalEdeExport;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EdeExport;
use App\Models\LibroDigital\EdeExportFile;
use App\Models\LibroDigital\EdeMapping;
use App\Models\LibroDigital\EdeValidationRun;
use App\Models\LibroDigital\EdeVersion;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\EdeExportService;
use App\Services\LibroDigital\EdeStandardImportService;
use App\Services\LibroDigital\FeatureFlagService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EdeController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly EdeExportService $exports,
        private readonly FeatureFlagService $features,
        private readonly EdeStandardImportService $standards,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function importStandard(ImportEdeStandardRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $source = $request->file('source');
        $schema = $request->file('schema');
        $mappings = $request->file('mappings');
        $result = $this->standards->import([
            'version' => $request->string('version')->toString(),
            'code' => $request->string('code', 'CEDS')->toString(),
            'source_path' => (string) $source?->getRealPath(),
            'schema_path' => (string) $schema?->getRealPath(),
            'mappings_path' => (string) $mappings?->getRealPath(),
            'authority' => $request->string('authority')->toString(),
            'source_url' => $request->string('source_url')->toString(),
            'effective_from' => $request->input('effective_from'),
            'activate' => false,
        ], $request->user());
        $version = $result['version']->loadCount(['mappings as active_mappings_count' => fn (Builder $query) => $query->where('active', true)]);
        if ($result['created']) {
            $this->audit->write(
                'lcd.ede.standard_imported',
                'import',
                $version,
                actor: $request->user(),
                schoolId: (int) $school->id,
                after: [
                    'code' => $version->code,
                    'version' => $version->version,
                    'status' => $version->status,
                    'source_hash' => $result['source_hash'],
                    'schema_hash' => $result['schema_hash'],
                    'mappings_hash' => $result['mappings_hash'],
                    'mapping_count' => $result['mapping_count'],
                ],
                request: $request,
            );
        }

        return $this->dataResponse([
            ...$this->versionPayload($version),
            'created' => $result['created'],
            'mappings_hash' => $result['mappings_hash'],
            'mapping_count' => $result['mapping_count'],
            'artifacts_archived_privately' => true,
            'activation_required' => true,
        ], $result['created'] ? 201 : 200);
    }

    public function versions(Request $request): JsonResponse
    {
        $this->assertAnyPermission($request, [
            'libro_digital.ede.manage', 'libro_digital.ede.export',
            'libro_digital.ede.validate', 'libro_digital.ede.download',
        ]);
        $this->school($request);

        return $this->collectionResponse(EdeVersion::query()
            ->withCount(['mappings as active_mappings_count' => fn (Builder $query) => $query->where('active', true)])
            ->orderByDesc('effective_from')->orderByDesc('id')->get()
            ->map(fn (EdeVersion $version): array => $this->versionPayload($version))->all());
    }

    public function mappings(ListEdeMappingsRequest $request): JsonResponse
    {
        $this->school($request);
        $mappings = EdeMapping::query()
            ->with('edeVersion:id,public_id,code,version,status')
            ->when($request->filled('ede_version_id'), fn (Builder $query) => $query->where('ede_version_id', $request->integer('ede_version_id')))
            ->when($request->filled('normative_profile_id'), fn (Builder $query) => $query->where('regulatory_profile_id', $request->integer('normative_profile_id')))
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                match ($request->string('status')->toString()) {
                    'active', 'mapped' => $query->where('active', true),
                    'inactive', 'blocked' => $query->where('active', false),
                    'missing' => $query->whereNull('source_field'),
                    default => null,
                };
            })
            ->when($request->filled('from'), fn (Builder $query) => $query->where(fn (Builder $dates) => $dates->whereNull('effective_to')->orWhere('effective_to', '>=', $request->date('from'))))
            ->when($request->filled('to'), fn (Builder $query) => $query->where(fn (Builder $dates) => $dates->whereNull('effective_from')->orWhere('effective_from', '<=', $request->date('to'))))
            ->orderBy('target_record_type')->orderBy('target_field')
            ->paginate($request->integer('per_page', 50));

        return $this->collectionResponse(
            $mappings->getCollection()->map(fn (EdeMapping $mapping): array => $this->mappingPayload($mapping))->all(),
            $this->pagination($mappings),
        );
    }

    public function index(ListEdeExportsRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $exports = EdeExport::query()->where('school_id', $school->id)
            ->with(['edeVersion:id,public_id,code,version', 'book:id,public_id,code'])
            ->when($request->filled('academic_year_id'), fn (Builder $query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('book_id'), fn (Builder $query) => $query->where('book_id', $request->integer('book_id')))
            ->when($request->filled('normative_profile_id'), fn (Builder $query) => $query->where('regulatory_profile_id', $request->integer('normative_profile_id')))
            ->when($request->filled('ede_version_id'), fn (Builder $query) => $query->where('ede_version_id', $request->integer('ede_version_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('requested_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('requested_at', '<=', $request->date('to')))
            ->latest('id')->paginate($request->integer('per_page', 50));

        return $this->collectionResponse(
            $exports->getCollection()->map(fn (EdeExport $export): array => $this->exportPayload($export))->all(),
            $this->pagination($exports),
        );
    }

    public function store(StoreEdeExportRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $book = $this->aggregate(Book::class, $request->input('book_id'));
        if ((int) $book->school_id !== (int) $school->id || ! $this->access->canAccessSchool($request->user(), (int) $book->school_id)) {
            throw new LibroDigitalException('El libro no pertenece al establecimiento seleccionado.', 'LCD_EDE_BOOK_SCOPE_INVALID', 403);
        }
        if ($request->filled('normative_profile_id') && (int) $request->integer('normative_profile_id') !== (int) $book->regulatory_profile_id) {
            throw new LibroDigitalException('El perfil solicitado no coincide con el perfil sellado del libro.', 'LCD_EDE_PROFILE_MISMATCH', 422);
        }
        $version = $this->aggregate(EdeVersion::class, $request->input('ede_version_id'));
        $export = $this->exports->request($school, $book, $version, $request->user(), [
            'export_type' => 'full',
        ]);

        return $this->dataResponse($this->exportPayload($export), 202, $this->modelVersion($export));
    }

    public function show(Request $request, string $edeExport): JsonResponse
    {
        $this->assertAnyPermission($request, [
            'libro_digital.ede.export', 'libro_digital.ede.validate', 'libro_digital.ede.download',
        ]);
        $export = $this->exportForRequest($request, $edeExport);
        $export->load(['edeVersion:id,public_id,code,version', 'book:id,public_id,code', 'validationRuns' => fn ($query) => $query->latest('id')->limit(10)]);

        return $this->dataResponse($this->exportPayload($export), version: $this->modelVersion($export));
    }

    public function validateExport(ValidateEdeExportRequest $request, string $edeExport): JsonResponse
    {
        $export = $this->exportForRequest($request, $edeExport);
        $expected = (int) ($request->header('If-Match') ?: $request->integer('lock_version'));
        $actual = $this->modelVersion($export);
        if ($expected !== $actual) {
            throw new VersionConflictException($expected, $actual);
        }

        $this->exports->assertCanValidate($export);
        $queued = DB::transaction(function () use ($export, $expected): EdeExport {
            $locked = EdeExport::query()->lockForUpdate()->findOrFail($export->id);
            $actual = $this->modelVersion($locked);
            if ($expected !== $actual) {
                throw new VersionConflictException($expected, $actual);
            }

            $status = $this->statusValue($locked->status);
            if (in_array($status, ['validation_queued', 'validating'], true)) {
                return $locked;
            }
            if (! in_array($status, ['generated', 'validation_failed'], true)) {
                throw new LibroDigitalException(
                    'La exportación debe estar generada antes de solicitar su validación.',
                    'LCD_EDE_STAGING_REQUIRED',
                    409,
                );
            }

            $locked->forceFill([
                'status' => 'validation_queued',
                'lock_version' => $actual + 1,
            ])->save();

            return $locked->fresh();
        }, 3);

        ValidateLibroDigitalEdeExport::dispatch($queued->id, $request->user()->id);

        return $this->dataResponse($this->exportPayload($queued), 202, $this->modelVersion($queued));
    }

    public function report(Request $request, string $edeExport): JsonResponse
    {
        $this->assertAnyPermission($request, ['libro_digital.ede.validate', 'libro_digital.ede.download']);
        $export = $this->exportForRequest($request, $edeExport);
        $run = $export->validationRuns()->with('results')->latest('id')->first();
        if (! $run) {
            throw new LibroDigitalException('La exportación todavía no tiene una corrida de validación.', 'LCD_EDE_VALIDATION_NOT_RUN', 409);
        }

        return $this->dataResponse($this->validationRunPayload($run));
    }

    public function download(Request $request, string $edeExport): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('libro_digital.ede.download'), 403);
        $export = $this->exportForRequest($request, $edeExport);
        $this->assertDownloadReady($export);
        $file = $export->files()->where('file_type', 'validated_package')->latest('id')->first();
        if (! $file) {
            throw new LibroDigitalException(
                'No existe un paquete EDE liberable; la proyección cifrada interna nunca se entrega.',
                'LCD_EDE_VALIDATED_PACKAGE_MISSING',
                409,
            );
        }

        return $this->privateDownload($file);
    }

    private function exportForRequest(Request $request, string $identifier): EdeExport
    {
        /** @var EdeExport $export */
        $export = $this->aggregate(EdeExport::class, $identifier);
        $school = $this->school($request);
        if ((int) $export->school_id !== (int) $school->id || ! $this->access->canAccessSchool($request->user(), (int) $export->school_id)) {
            abort(403);
        }

        return $export;
    }

    private function assertDownloadReady(EdeExport $export): void
    {
        $this->exports->assertCanRelease($export);
        if (! $this->features->enabled('lcd_fiscalization_download_enabled', (int) $export->school_id)) {
            throw new LibroDigitalException('La descarga de fiscalización permanece deshabilitada.', 'LCD_FISCALIZATION_DOWNLOAD_DISABLED', 409);
        }
        if ($this->statusValue($export->status) !== 'released'
            || $export->validator_status !== 'passed'
            || ! $export->validated_at) {
            throw new LibroDigitalException('Solo una exportación validada y liberada por un flujo separado puede descargarse.', 'LCD_EDE_DOWNLOAD_NOT_RELEASED', 409);
        }
        $latestCheck = $export->validationRuns()->where('status', 'completed')->whereNotNull('report_hash')->latest('id')->first();
        if (! $latestCheck || ! $latestCheck->results()->where('code', 'EDE_CHECK_PASSED')->exists()) {
            throw new LibroDigitalException('No existe evidencia contractual de un check exitoso.', 'LCD_EDE_CHECK_EVIDENCE_REQUIRED', 409);
        }
    }

    private function privateDownload(EdeExportFile $file): StreamedResponse
    {
        if ($file->encrypted) {
            throw new LibroDigitalException('Los artefactos internos cifrados no son descargables.', 'LCD_EDE_INTERNAL_FILE_NOT_DOWNLOADABLE', 409);
        }
        $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
        if (! $disk->exists($file->private_path)) {
            throw new LibroDigitalException('El paquete no existe en almacenamiento privado.', 'LCD_EDE_FILE_MISSING', 404);
        }
        $contents = $disk->get($file->private_path);
        if (! hash_equals((string) $file->sha256, hash('sha256', $contents))) {
            throw new LibroDigitalException('El paquete no supera la verificación de integridad.', 'LCD_EDE_FILE_HASH_MISMATCH', 409);
        }

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            basename($file->file_name),
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    /** @param list<string> $permissions */
    private function assertAnyPermission(Request $request, array $permissions): void
    {
        abort_unless(collect($permissions)->contains(fn (string $permission): bool => $request->user()?->hasPermission($permission) ?? false), 403);
    }

    /** @return array<string, mixed> */
    private function versionPayload(EdeVersion $version): array
    {
        return [
            'id' => $version->id, 'public_id' => $version->public_id, 'code' => $version->code,
            'name' => $version->code, 'version' => $version->version, 'authority' => $version->authority,
            'source_url' => $version->source_url, 'source_hash' => $version->source_hash,
            'schema_hash' => $version->schema_hash, 'status' => $version->status,
            'is_active' => $version->status === 'active', 'effective_from' => $version->effective_from?->toDateString(),
            'effective_to' => $version->effective_to?->toDateString(), 'active_mappings_count' => (int) ($version->active_mappings_count ?? 0),
            'blockers' => $this->versionBlockers($version),
        ];
    }

    /** @return list<array{code: string, reason: string}> */
    private function versionBlockers(EdeVersion $version): array
    {
        $blockers = [];
        if (! $version->source_hash || ! $version->schema_hash) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_VERSION_HASH', 'reason' => 'La versión no tiene hashes de fuente y esquema completos.'];
        }
        if ((int) ($version->active_mappings_count ?? $version->mappings()->where('active', true)->count()) === 0) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_MAPPINGS', 'reason' => 'La versión no tiene mapeos activos.'];
        }
        if (! config('libro_digital.ede.enabled')) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_DISABLED', 'reason' => 'La capacidad EDE está apagada.'];
        }
        if (! preg_match('/^sha256:[a-f0-9]{64}$/', (string) config('libro_digital.ede.validator_digest'))) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_VALIDATOR_DIGEST', 'reason' => 'No existe un digest oficial fijado.'];
        }

        return $blockers;
    }

    /** @return array<string, mixed> */
    private function mappingPayload(EdeMapping $mapping): array
    {
        return [
            'id' => $mapping->id, 'public_id' => $mapping->public_id, 'ede_version_id' => $mapping->ede_version_id,
            'ede_version' => $mapping->edeVersion ? ['id' => $mapping->edeVersion->id, 'code' => $mapping->edeVersion->code, 'version' => $mapping->edeVersion->version] : null,
            'normative_profile_id' => $mapping->regulatory_profile_id, 'code' => $mapping->code,
            'source_entity' => $mapping->source_entity, 'source_field' => $mapping->source_field,
            'target_record_type' => $mapping->target_record_type, 'target_field' => $mapping->target_field,
            'data_type' => $mapping->data_type, 'required' => (bool) $mapping->required,
            'mapping_hash' => $mapping->mapping_hash, 'active' => (bool) $mapping->active,
            'status' => ! $mapping->active ? 'blocked' : ($mapping->source_field || $mapping->default_value !== null ? 'mapped' : 'missing'),
        ];
    }

    /** @return array<string, mixed> */
    private function exportPayload(EdeExport $export): array
    {
        $file = $export->files()->where('file_type', 'validated_package')->latest('id')->first();

        return [
            'id' => $export->id, 'public_id' => $export->public_id, 'report_identifier' => $export->public_id,
            'school_id' => $export->school_id, 'academic_year_id' => $export->academic_year_id,
            'book_id' => $export->book_id, 'ede_version_id' => $export->ede_version_id,
            'ede_version' => $export->edeVersion ? ['id' => $export->edeVersion->id, 'code' => $export->edeVersion->code, 'version' => $export->edeVersion->version] : null,
            'export_type' => $export->export_type, 'scope' => $export->scope_snapshot,
            'status' => $this->statusValue($export->status), 'validation_status' => $export->validator_status,
            'source_snapshot_hash' => $export->source_snapshot_hash, 'manifest_hash' => $export->manifest_hash,
            'record_count' => (int) $export->record_count, 'warning_count' => (int) $export->warning_count,
            'error_count' => (int) $export->error_count, 'validator_version' => $export->validator_version,
            'filename' => $file?->file_name, 'sha256' => $file?->sha256 ?: $export->manifest_hash,
            'download_url' => $file ? url('/api/libro-digital/v1/ede/exports/'.$export->public_id.'/download') : null,
            'requested_at' => $export->requested_at?->toIso8601String(), 'generated_at' => $export->generated_at?->toIso8601String(),
            'validated_at' => $export->validated_at?->toIso8601String(), 'released_at' => $export->released_at?->toIso8601String(),
            'error_summary' => $export->error_summary, 'lock_version' => $this->modelVersion($export),
        ];
    }

    /** @return array<string, mixed> */
    private function validationRunPayload(EdeValidationRun $run): array
    {
        $run->loadMissing('results');

        return [
            'id' => $run->id, 'public_id' => $run->public_id, 'ede_export_id' => $run->ede_export_id,
            'validator_name' => $run->validator_name, 'validator_version' => $run->validator_version,
            'validator_image_digest' => $run->validator_image_digest, 'status' => $run->status,
            'exit_code' => $run->exit_code, 'report_hash' => $run->report_hash,
            'started_at' => $run->started_at?->toIso8601String(), 'completed_at' => $run->completed_at?->toIso8601String(),
            'results' => $run->results->map(fn ($result): array => [
                'severity' => $result->severity, 'code' => $result->code, 'record_type' => $result->record_type,
                'record_reference' => $result->record_reference, 'field' => $result->field, 'message' => $result->message,
            ])->all(),
        ];
    }

    private function modelVersion(EdeExport $export): int
    {
        return max(1, (int) $export->lock_version);
    }

    /** @return array<string, int> */
    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
        ];
    }
}
