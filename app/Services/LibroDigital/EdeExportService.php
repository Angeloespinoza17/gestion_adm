<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Jobs\GenerateLibroDigitalEdeExport;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EdeExport;
use App\Models\LibroDigital\EdeExportFile;
use App\Models\LibroDigital\EdeVersion;
use App\Models\LibroDigital\School;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EdeExportService
{
    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly FeatureFlagService $features,
        private readonly EdeProjectionService $projector,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @param array<string, mixed> $scope */
    public function request(School $school, Book $book, EdeVersion $version, User $actor, array $scope = []): EdeExport
    {
        $this->assertReady($school, $book, $version);
        $scope = ['book_public_id' => $book->public_id, 'book_revision' => $book->revision, ...$scope];
        $dedupe = $this->canonical->hash([
            'school' => $school->id,
            'book' => $book->id,
            'revision' => $book->revision,
            'ede_version' => $version->id,
            'scope' => $scope,
        ]);

        $export = EdeExport::query()->firstOrCreate(
            ['school_id' => $school->id, 'deduplication_key' => $dedupe],
            [
                'academic_year_id' => $book->academic_year_id,
                'book_id' => $book->id,
                'regulatory_profile_id' => $book->regulatory_profile_id,
                'ede_version_id' => $version->id,
                'export_type' => 'full',
                'scope_snapshot' => $scope,
                'status' => 'requested',
                'requested_by' => $actor->id,
                'requested_at' => now('UTC'),
            ],
        );

        if ($export->wasRecentlyCreated) {
            $this->audit->write('lcd.ede_export.requested', 'request', $export, actor: $actor, schoolId: $school->id, academicYearId: $book->academic_year_id, after: $export->only(['public_id', 'status', 'scope_snapshot', 'deduplication_key']));
            GenerateLibroDigitalEdeExport::dispatch($export->id);
        } elseif ($this->status($export->status) === 'preflight_failed') {
            $this->transition($export, [
                'status' => 'requested',
                'error_summary' => null,
                'requested_by' => $actor->id,
                'requested_at' => now('UTC'),
            ]);
            GenerateLibroDigitalEdeExport::dispatch($export->id);
        }

        return $export->fresh();
    }

    public function generate(EdeExport $export): void
    {
        $export->loadMissing(['book.school', 'book.academicYear', 'edeVersion', 'requester']);
        $this->transition($export, ['status' => 'preflight_running']);
        $this->assertReady($export->book->school, $export->book, $export->edeVersion);
        $this->transition($export, ['status' => 'projecting']);
        $projection = $this->projector->project($export->book, $export->edeVersion);
        $this->transition($export, ['status' => 'projected']);
        $plaintext = $this->canonical->encode([
            'manifest' => $projection['manifest'],
            'records' => $projection['records'],
        ]);
        // El staging contiene datos personales: se cifra aun dentro del disco privado.
        $ciphertext = Crypt::encryptString($plaintext);
        $this->transition($export, ['status' => 'packaging']);
        $path = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/')
            .'/ede/'.$export->school->public_id.'/'.$export->public_id.'/projection.json.enc';
        Storage::disk((string) config('libro_digital.storage.disk', 'local'))->put($path, $ciphertext);

        EdeExportFile::query()->create([
            'ede_export_id' => $export->id,
            'file_type' => 'projection_staging',
            'file_name' => 'projection.json.enc',
            'private_path' => $path,
            'mime_type' => 'application/octet-stream',
            'size_bytes' => strlen($ciphertext),
            'sha256' => hash('sha256', $ciphertext),
            'encrypted' => true,
            'encryption_metadata' => ['scheme' => 'laravel-aead', 'key_reference' => 'APP_KEY'],
        ]);

        $this->transition($export, [
            'status' => 'generated',
            'source_snapshot_hash' => $projection['manifest']['records_hash'],
            'manifest' => $projection['manifest'],
            'manifest_hash' => $projection['manifest']['manifest_hash'],
            'record_count' => array_sum($projection['manifest']['record_counts']),
            'validator_status' => 'not_run',
            'generated_at' => now('UTC'),
        ]);

        $this->audit->write('lcd.ede_export.generated_unvalidated', 'generate', $export, actor: $export->requester, schoolId: $export->school_id, academicYearId: $export->academic_year_id, after: $export->only(['public_id', 'status', 'manifest_hash', 'source_snapshot_hash', 'record_count', 'validator_status']));
    }

    /**
     * Revalida los mismos bloqueadores al iniciar una validación o entregar un
     * artefacto. Una exportación histórica no puede eludir un blocker abierto
     * después de haber sido generada.
     */
    public function assertCanValidate(EdeExport $export): void
    {
        $export->loadMissing(['school', 'book', 'edeVersion']);
        if (! $export->book || ! $export->edeVersion || ! $export->school) {
            throw new LibroDigitalException(
                'La exportación EDE no conserva todo el contexto requerido.',
                'LCD_EDE_CONTEXT_REQUIRED',
                409,
            );
        }

        $this->assertReady($export->school, $export->book, $export->edeVersion);
        if (! in_array($this->status($export->status), ['generated', 'validation_failed', 'validation_queued', 'validating'], true)) {
            throw new LibroDigitalException(
                'La exportación no está en un estado que permita validación.',
                'LCD_EDE_STAGING_REQUIRED',
                409,
            );
        }
        $staging = $export->files()->where('file_type', 'projection_staging')->latest('id')->first();
        if (! $staging) {
            throw new LibroDigitalException('La exportación no posee una proyección cifrada.', 'LCD_EDE_STAGING_REQUIRED', 409);
        }
        $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
        if (! $disk->exists($staging->private_path)) {
            throw new LibroDigitalException('La proyección cifrada no existe en el almacenamiento privado.', 'LCD_EDE_STAGING_MISSING', 409);
        }
        $ciphertext = $disk->get($staging->private_path);
        if (! hash_equals((string) $staging->sha256, hash('sha256', $ciphertext))) {
            throw new LibroDigitalException('La proyección cifrada no supera su verificación de integridad.', 'LCD_EDE_STAGING_HASH_MISMATCH', 409);
        }
    }

    /**
     * La liberación tiene un umbral más alto que generar o validar: ningún
     * blocker EDE pendiente puede ignorarse una vez que se entrega evidencia.
     */
    public function assertCanRelease(EdeExport $export): void
    {
        $export->loadMissing(['school', 'book', 'edeVersion']);
        if (! $export->book || ! $export->edeVersion || ! $export->school) {
            throw new LibroDigitalException('La exportación EDE no conserva todo el contexto requerido.', 'LCD_EDE_CONTEXT_REQUIRED', 409);
        }

        $this->assertReady($export->school, $export->book, $export->edeVersion, false);
        if ($this->hasOpenComplianceBlocker('lcd_fiscalization_download_enabled')) {
            throw new LibroDigitalException(
                'La descarga fiscalizadora mantiene bloqueadores institucionales abiertos.',
                'LCD_FISCALIZATION_COMPLIANCE_BLOCKED',
                409,
            );
        }
    }

    private function assertReady(School $school, Book $book, EdeVersion $version, bool $allowPendingValidatorEvidence = true): void
    {
        $blockers = [];
        if (! config('libro_digital.ede.enabled') || ! $this->features->enabled('lcd_ede_export_enabled', $school->id)) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_DISABLED', 'reason' => 'La exportación EDE permanece deshabilitada por feature flag.'];
        }
        if (! preg_match('/^sha256:[a-f0-9]{64}$/', (string) config('libro_digital.ede.validator_digest'))) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_VALIDATOR_DIGEST', 'reason' => 'No se ha fijado el digest del validador oficial.'];
        }
        if (! config('libro_digital.ede.command_contract_verified')) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_COMMAND_CONTRACT', 'reason' => 'El contrato parse/insert/check no ha sido archivado y verificado.'];
        }
        if (! $version->source_hash || ! $version->schema_hash || $version->status !== 'active') {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_VERSION', 'reason' => 'La versión EDE no está activa o carece de hashes verificables.'];
        }
        if (! $version->mappings()->where('active', true)->exists()) {
            $blockers[] = ['code' => 'COMPLIANCE_BLOCKER_EDE_MAPPINGS', 'reason' => 'No existen mapeos oficiales versionados activos.'];
        }
        $configured = DB::table('lcd_settings')->where('scope_key', 'compliance')->where('key', 'open_blockers')->value('value');
        foreach ((array) json_decode((string) $configured, true) as $item) {
            // Esta evidencia solo puede obtenerse después de generar un candidato
            // y ejecutar check; se exige para liberar, no para crear el candidato.
            if (($item['feature_flag'] ?? null) === 'lcd_ede_export_enabled'
                && (! $allowPendingValidatorEvidence || ($item['code'] ?? null) !== 'EDE_VALIDATOR_NOT_EXECUTED')) {
                $blockers[] = ['code' => (string) ($item['code'] ?? 'COMPLIANCE_BLOCKER_EDE'), 'reason' => 'Bloqueador normativo abierto.'];
            }
        }

        if ($blockers !== []) {
            throw new LibroDigitalException('La exportación EDE no puede ejecutarse hasta cerrar los bloqueadores de cumplimiento.', 'LCD_EDE_COMPLIANCE_BLOCKED', 409, $blockers);
        }
        if ($book->school_id !== $school->id) {
            throw new LibroDigitalException('El libro no pertenece al establecimiento seleccionado.', 'LCD_BOOK_SCHOOL_MISMATCH', 403);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function transition(EdeExport $export, array $attributes): void
    {
        $export->forceFill([
            ...$attributes,
            'lock_version' => ((int) $export->lock_version) + 1,
        ])->save();
    }

    private function status(mixed $status): string
    {
        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }

    private function hasOpenComplianceBlocker(string $featureFlag): bool
    {
        $configured = DB::table('lcd_settings')
            ->where('scope_key', 'compliance')
            ->where('key', 'open_blockers')
            ->value('value');

        return collect(json_decode((string) $configured, true) ?: [])->contains(
            fn (array $item): bool => ($item['feature_flag'] ?? null) === $featureFlag,
        );
    }
}
