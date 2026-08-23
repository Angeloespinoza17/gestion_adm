<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Contracts\LibroDigital\Curriculum\CurriculumDocumentClassifierInterface;
use App\Contracts\LibroDigital\Curriculum\PdfOcrExtractorInterface;
use App\Contracts\LibroDigital\Curriculum\PdfTextExtractorInterface;
use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Jobs\ProcessCurriculumImportFile;
use App\Models\AcademicYear;
use App\Models\LibroDigital\CurriculumDocument;
use App\Models\LibroDigital\CurriculumDocumentPage;
use App\Models\LibroDigital\CurriculumDocumentSection;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportCandidate;
use App\Models\LibroDigital\CurriculumImportConflict;
use App\Models\LibroDigital\CurriculumImportFile;
use App\Models\LibroDigital\CurriculumImportLog;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\School;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CurriculumPdfImportService
{
    private const STAGES = [
        'uploaded' => 0,
        'validating' => 10,
        'extracting_text' => 25,
        'running_ocr' => 40,
        'detecting_structure' => 55,
        'extracting_entities' => 70,
        'normalizing' => 80,
        'reconciling' => 90,
        'pending_review' => 100,
    ];

    public function __construct(
        private readonly PdfTextExtractorInterface $textExtractor,
        private readonly PdfOcrExtractorInterface $ocrExtractor,
        private readonly CurriculumDocumentClassifierInterface $classifier,
        private readonly CurriculumDocumentParserRegistry $parsers,
        private readonly PdfTextNormalizer $normalizer,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  array<string,mixed>  $hints
     * @return array{batch:?CurriculumImportBatch,files:list<CurriculumImportFile>,duplicates:list<CurriculumImportFile>}
     */
    public function upload(array $files, School $school, AcademicYear $year, User $actor, Request $request, array $hints = []): array
    {
        if ($files === []) {
            throw new LibroDigitalException('Selecciona al menos un PDF ministerial.', 'LCD_CURRICULUM_PDF_REQUIRED', 422);
        }
        $validated = [];
        $duplicates = [];
        foreach ($files as $file) {
            $metadata = $this->validatePdf($file);
            $existing = CurriculumImportFile::query()
                ->where('school_id', $school->id)
                ->where('sha256', $metadata['sha256'])
                ->first();
            if ($existing) {
                $duplicates[] = $existing;

                continue;
            }
            $validated[] = ['file' => $file, ...$metadata];
        }
        if ($validated === []) {
            return ['batch' => null, 'files' => [], 'duplicates' => $duplicates];
        }

        $batchHash = hash('sha256', $this->canonical->encode(collect($validated)->pluck('sha256')->sort()->values()->all()));
        $idempotencyKey = hash('sha256', trim((string) $request->header('Idempotency-Key')) ?: $batchHash);
        $existingBatch = CurriculumImportBatch::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where(fn ($query) => $query->where('source_hash', $batchHash)->orWhere('idempotency_key', $idempotencyKey))
            ->first();
        if ($existingBatch) {
            return [
                'batch' => $existingBatch,
                'files' => $existingBatch->files()->get()->all(),
                'duplicates' => $duplicates,
            ];
        }

        [$batch, $createdFiles] = DB::transaction(function () use ($validated, $school, $year, $actor, $request, $hints, $batchHash, $idempotencyKey): array {
            $batch = CurriculumImportBatch::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'idempotency_key' => $idempotencyKey,
                'status' => CurriculumImportBatch::STATUS_PROCESSING,
                'catalog_code' => 'MINEDUC-PDF-PROGRAMS',
                'catalog_version' => now('UTC')->format('Ymd-His'),
                'import_format' => count($validated) > 1 ? 'pdf_batch' : 'pdf',
                'format_version' => 'lcd-curriculum-pdf/v1',
                'original_name' => count($validated) > 1 ? count($validated).' documentos PDF' : $validated[0]['file']->getClientOriginalName(),
                'detected_mime_type' => 'application/pdf',
                'size_bytes' => collect($validated)->sum('size_bytes'),
                'disk' => (string) config('libro_digital.storage.disk', 'local'),
                'private_path' => trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/').'/curriculum-programs/'.$school->public_id.'/'.$year->year.'/'.$batchHash,
                'storage_metadata' => ['encrypted' => true, 'file_count' => count($validated)],
                'source_hash' => $batchHash,
                'manifest' => [
                    'format' => 'lcd-curriculum-pdf/v1',
                    'file_hashes' => collect($validated)->pluck('sha256')->values()->all(),
                    'hints' => $hints,
                ],
                'manifest_hash' => hash('sha256', $this->canonical->encode(['files' => collect($validated)->pluck('sha256')->sort()->values()->all(), 'hints' => $hints])),
                'total_row_count' => count($validated),
                'valid_row_count' => count($validated),
                'requested_by' => $actor->id,
                'requested_at' => now('UTC'),
                'metadata' => ['correlation_id' => $request->attributes->get('lcd_correlation_id'), 'hints' => $hints],
            ]);
            $createdFiles = [];
            foreach ($validated as $entry) {
                $privatePath = $this->archive($entry['file'], $school, $year, $entry['sha256']);
                $createdFiles[] = CurriculumImportFile::query()->create([
                    'school_id' => $school->id,
                    'academic_year_id' => $year->id,
                    'import_batch_id' => $batch->id,
                    'original_name' => mb_substr($entry['file']->getClientOriginalName(), 0, 190),
                    'detected_mime_type' => $entry['mime_type'],
                    'size_bytes' => $entry['size_bytes'],
                    'sha256' => $entry['sha256'],
                    'disk' => (string) config('libro_digital.storage.disk', 'local'),
                    'private_path' => $privatePath,
                    'status' => 'uploaded',
                    'progress' => 0,
                    'current_stage' => 'uploaded',
                    'uploaded_by' => $actor->id,
                    'detected_metadata' => ['hints' => $hints],
                ]);
            }

            return [$batch, $createdFiles];
        }, 3);

        $this->audit->write(
            'curriculum.program_import.uploaded',
            'upload',
            $batch,
            actor: $actor,
            schoolId: $school->id,
            academicYearId: $year->id,
            after: ['public_id' => $batch->public_id, 'file_count' => count($createdFiles), 'source_hash' => $batchHash],
            request: $request,
        );
        foreach ($createdFiles as $createdFile) {
            ProcessCurriculumImportFile::dispatch($createdFile->id)
                ->onQueue((string) config('libro_digital.curriculum_import.queue', 'curriculum-imports'));
        }

        return ['batch' => $batch, 'files' => $createdFiles, 'duplicates' => $duplicates];
    }

    /** @return array{sha256:string,size_bytes:int,mime_type:string,private_path:string,disk:string} */
    public function archiveManualSource(UploadedFile $file, School $school, AcademicYear $year): array
    {
        $metadata = $this->validatePdf($file);

        return [
            ...$metadata,
            'private_path' => $this->archive($file, $school, $year, $metadata['sha256']),
            'disk' => (string) config('libro_digital.storage.disk', 'local'),
        ];
    }

    public function process(CurriculumImportFile $file, bool $force = false): CurriculumImportFile
    {
        $file->refresh();
        if (! $force && in_array($file->status, CurriculumImportFile::TERMINAL_STATUSES, true)) {
            return $file;
        }
        if ($file->cancel_requested_at) {
            $file->forceFill(['status' => 'archived', 'current_stage' => 'archived'])->save();

            return $file;
        }

        $temp = null;
        try {
            $this->advance($file, 'validating', 'Validando firma, integridad y almacenamiento privado.');
            $temp = $this->temporaryDecryptedPdf($file);
            if (! hash_equals((string) $file->sha256, hash_file('sha256', $temp))) {
                throw new LibroDigitalException('La copia privada no coincide con su SHA-256.', 'LCD_CURRICULUM_PDF_HASH_MISMATCH', 409);
            }

            $this->advance($file, 'extracting_text', 'Extrayendo texto y distribución página por página.');
            $extracted = $this->textExtractor->extract($temp);
            $pages = $extracted['pages'];
            if ($pages === []) {
                throw new LibroDigitalException('El PDF no contiene páginas interpretables.', 'LCD_CURRICULUM_PDF_EMPTY', 422);
            }

            $this->advance($file, 'running_ocr', 'Evaluando páginas sin capa textual suficiente.');
            $ocrUnavailablePages = [];
            foreach ($pages as &$page) {
                if (mb_strlen((string) ($page['normalized_text'] ?? '')) >= (int) config('libro_digital.curriculum_import.ocr_min_text_chars', 40)) {
                    continue;
                }
                if ($this->ocrExtractor->available()) {
                    $ocr = $this->ocrExtractor->extractPage($temp, (int) $page['physical_page_number']);
                    $page['ocr_text'] = $ocr['text'];
                    $page['normalized_text'] = $this->normalizer->normalize($ocr['text']);
                    $page['extraction_method'] = 'ocr';
                    $page['confidence'] = $ocr['confidence'];
                    $page['processing_warnings'] = $ocr['warnings'];
                } else {
                    $page['processing_warnings'] = array_values(array_unique([...(array) $page['processing_warnings'], 'ocr_runtime_unavailable']));
                    $ocrUnavailablePages[] = (int) $page['physical_page_number'];
                }
            }
            unset($page);

            $this->advance($file, 'detecting_structure', 'Clasificando documento, asignatura, curso y versión.');
            $classification = $this->classifier->classify($pages, (array) data_get($file->detected_metadata, 'hints', []));
            $this->advance($file, 'extracting_entities', 'Detectando secciones, unidades, objetivos y relaciones.');
            $parsed = $this->parsers->for((string) $classification['document_type'])->parse($classification, $pages);
            $this->advance($file, 'normalizing', 'Normalizando candidatos y conservando evidencia de origen.');

            DB::transaction(function () use ($file, $classification, $extracted, $pages, $parsed, $ocrUnavailablePages): void {
                $document = CurriculumDocument::query()->updateOrCreate(
                    ['sha256' => $file->sha256],
                    [
                        'curriculum_version_id' => null,
                        'document_type' => $classification['document_type'],
                        'title' => mb_substr((string) ($classification['title'] ?: $file->original_name), 0, 190),
                        'subtitle' => null,
                        'original_filename' => $file->original_name,
                        'disk' => $file->disk,
                        'storage_path' => $file->private_path,
                        'mime_type' => $file->detected_mime_type,
                        'file_size' => $file->size_bytes,
                        'official_url' => data_get($file->detected_metadata, 'hints.official_url'),
                        'issuing_authority' => $classification['issuing_authority'],
                        'decree' => $classification['decree'],
                        'edition' => $classification['edition'],
                        'publication_year' => $classification['publication_year'],
                        'page_count' => count($pages),
                        'primary_subject_id' => $classification['schedule_subject_id'],
                        'primary_education_level_id' => $classification['education_level_id'],
                        'extraction_status' => 'pending_review',
                        'review_status' => 'pending',
                        'classification' => $classification,
                        'metadata' => ['pdf_details' => $extracted['details'], 'parser_summary' => $parsed['summary']],
                        'uploaded_by' => $file->uploaded_by,
                    ],
                );
                $file->forceFill(['curriculum_document_id' => $document->id])->save();
                foreach ($pages as $page) {
                    CurriculumDocumentPage::query()->updateOrCreate(
                        ['curriculum_document_id' => $document->id, 'physical_page_number' => $page['physical_page_number']],
                        collect($page)->except('physical_page_number')->all(),
                    );
                }
                foreach ($parsed['sections'] as $section) {
                    CurriculumDocumentSection::query()->updateOrCreate(
                        [
                            'curriculum_document_id' => $document->id,
                            'section_type' => $section['section_type'],
                            'page_start' => $section['page_start'],
                            'official_order' => $section['official_order'],
                        ],
                        collect($section)->except(['parent_section_id'])->all(),
                    );
                }
                $this->storeCandidates($file, $parsed['candidates']);
                $warnings = array_values(array_unique([...(array) $parsed['warnings'], ...($ocrUnavailablePages === [] ? [] : ['ocr_unavailable_for_low_text_pages'])]));
                $metadata = (array) $file->detected_metadata;
                $metadata['classification'] = $classification;
                $metadata['parser_summary'] = $parsed['summary'];
                $metadata['ocr_unavailable_pages'] = $ocrUnavailablePages;
                $file->forceFill(['detected_metadata' => $metadata, 'warnings' => $warnings])->save();
            }, 3);

            $this->advance($file, 'reconciling', 'Conciliando con asignaturas, cursos y OA existentes.');
            $this->reconcile($file->fresh(['document', 'candidates']));
            $critical = CurriculumImportConflict::query()->where('import_file_id', $file->id)->where('severity', 'critical')->where('status', 'open')->count();
            $file->forceFill([
                'status' => 'pending_review',
                'current_stage' => 'pending_review',
                'progress' => 100,
                'last_valid_stage_order' => self::STAGES['pending_review'],
                'processing_completed_at' => now('UTC'),
                'error_summary' => null,
                'failed_at' => null,
            ])->save();
            $this->log($file, 'pending_review', $critical > 0 ? 'Extracción completa con conflictos críticos pendientes.' : 'Extracción y conciliación completas; requiere confirmación humana.', $critical > 0 ? 'warning' : 'info', 100, ['critical_conflicts' => $critical]);
            $this->refreshBatch($file->batch);

            return $file->fresh(['document', 'candidates', 'conflicts']);
        } catch (Throwable $exception) {
            $file->forceFill([
                'status' => 'failed',
                'current_stage' => $file->current_stage ?: 'validating',
                'failed_at' => now('UTC'),
                'error_summary' => mb_strimwidth($exception->getMessage(), 0, 1800),
            ])->save();
            $this->log($file, (string) $file->current_stage, $exception->getMessage(), 'error', (int) $file->progress, [
                'code' => $exception instanceof LibroDigitalException ? $exception->errorCode : 'LCD_CURRICULUM_PDF_PROCESSING_FAILED',
            ]);
            $this->refreshBatch($file->batch);

            throw $exception;
        } finally {
            if (is_string($temp) && is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    /** @return array{sha256:string,size_bytes:int,mime_type:string} */
    private function validatePdf(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! is_string($path) || ! is_readable($path)) {
            throw new LibroDigitalException('No se pudo leer el PDF cargado.', 'LCD_CURRICULUM_PDF_UNREADABLE', 422);
        }
        $size = (int) filesize($path);
        $maximum = (int) config('libro_digital.curriculum_import.max_pdf_kb', config('libro_digital.storage.max_file_kb', 20480)) * 1024;
        if ($size < 5 || $size > $maximum) {
            throw new LibroDigitalException('El PDF supera el tamaño máximo configurado o está vacío.', 'LCD_CURRICULUM_PDF_SIZE_INVALID', 422);
        }
        $handle = fopen($path, 'rb');
        $signature = is_resource($handle) ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw new LibroDigitalException('La firma real del archivo no corresponde a PDF.', 'LCD_CURRICULUM_PDF_SIGNATURE_INVALID', 422);
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($mime, ['application/pdf', 'application/octet-stream'], true)) {
            throw new LibroDigitalException('El MIME real del archivo no corresponde a PDF.', 'LCD_CURRICULUM_PDF_MIME_INVALID', 422);
        }

        return ['sha256' => hash_file('sha256', $path), 'size_bytes' => $size, 'mime_type' => 'application/pdf'];
    }

    private function archive(UploadedFile $file, School $school, AcademicYear $year, string $hash): string
    {
        $contents = file_get_contents((string) $file->getRealPath());
        if (! is_string($contents)) {
            throw new LibroDigitalException('No se pudo archivar el PDF.', 'LCD_CURRICULUM_PDF_ARCHIVE_FAILED', 500);
        }
        $path = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/')
            .'/curriculum-programs/'.$school->public_id.'/'.$year->year.'/'.$hash.'.pdf.enc';
        $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
        if (! $disk->exists($path) && ! $disk->put($path, Crypt::encryptString($contents))) {
            throw new LibroDigitalException('No fue posible guardar el PDF en almacenamiento privado.', 'LCD_CURRICULUM_PDF_ARCHIVE_FAILED', 500);
        }

        return $path;
    }

    private function temporaryDecryptedPdf(CurriculumImportFile $file): string
    {
        $encrypted = Storage::disk($file->disk)->get($file->private_path);
        $contents = Crypt::decryptString($encrypted);
        $path = tempnam(sys_get_temp_dir(), 'lcd-curriculum-');
        if (! is_string($path) || file_put_contents($path, $contents) === false) {
            throw new LibroDigitalException('No se pudo preparar la copia temporal privada.', 'LCD_CURRICULUM_PDF_TEMP_FAILED', 500);
        }

        return $path;
    }

    /** @param list<array<string,mixed>> $candidates */
    private function storeCandidates(CurriculumImportFile $file, array $candidates): void
    {
        $byKey = [];
        foreach ($candidates as $candidate) {
            $parentKey = $candidate['parent_key'] ?? null;
            unset($candidate['parent_key']);
            $model = CurriculumImportCandidate::query()->updateOrCreate(
                ['import_file_id' => $file->id, 'candidate_key' => $candidate['candidate_key']],
                [
                    ...collect($candidate)->except('candidate_key')->all(),
                    'parent_candidate_id' => null,
                    'review_status' => 'pending',
                ],
            );
            $byKey[$candidate['candidate_key']] = ['model' => $model, 'parent_key' => $parentKey];
        }
        foreach ($byKey as $entry) {
            $parentKey = $entry['parent_key'];
            if ($parentKey && isset($byKey[$parentKey])) {
                $entry['model']->forceFill(['parent_candidate_id' => $byKey[$parentKey]['model']->id])->save();
            }
        }
    }

    private function reconcile(CurriculumImportFile $file): void
    {
        $classification = (array) data_get($file->detected_metadata, 'classification', []);
        foreach (['schedule_subject_id' => 'Asignatura', 'education_level_id' => 'Curso', 'grade_code' => 'Código de grado'] as $field => $label) {
            if (filled($classification[$field] ?? null)) {
                continue;
            }
            $this->conflict($file, null, 'classification_missing', 'critical', $label.' no detectado', 'Debe confirmarse antes de validar o publicar.', ['field' => $field]);
        }
        if ((float) data_get($classification, 'confidence.subject', 0) < 0.75 || (float) data_get($classification, 'confidence.grade', 0) < 0.75) {
            $this->conflict($file, null, 'classification_low_confidence', 'critical', 'Clasificación insegura', 'La asignatura o el curso requieren revisión humana.', ['confidence' => $classification['confidence'] ?? []]);
        }

        $subjectId = (int) ($classification['schedule_subject_id'] ?? 0);
        $grade = (string) ($classification['grade_code'] ?? '');
        $objectivePool = $subjectId && $grade
            ? LearningObjective::query()->where('schedule_subject_id', $subjectId)->where('grade_code', $grade)->get()
            : collect();
        foreach ($file->candidates as $candidate) {
            if (! in_array($candidate->entity_type, ['learning_objective', 'skill_objective'], true)) {
                continue;
            }
            $type = (string) data_get($candidate->structured_payload, 'objective_type', $candidate->entity_type === 'skill_objective' ? 'OAH' : 'OA');
            $official = (string) data_get($candidate->structured_payload, 'official_code', $candidate->detected_value);
            preg_match('/(?:OA|OAH)?\s*0*([0-9]+|[a-z])$/iu', trim($official), $codeMatch);
            $suffix = mb_strtolower((string) ($codeMatch[1] ?? ''));
            $matches = $objectivePool->filter(function (LearningObjective $objective) use ($type, $suffix): bool {
                if (mb_strtoupper((string) $objective->objective_type) !== mb_strtoupper($type)) {
                    return false;
                }
                preg_match('/(?:OA|OAH)\s*0*([0-9]+|[a-z])$/iu', trim((string) $objective->code), $match);

                return mb_strtolower((string) ($match[1] ?? '')) === $suffix;
            });
            if ($matches->count() === 1) {
                $candidate->forceFill([
                    'suggested_existing_type' => LearningObjective::class,
                    'suggested_existing_id' => $matches->first()->id,
                    'suggested_action' => 'reuse',
                ])->save();
            } elseif ($matches->count() > 1) {
                $this->conflict($file, $candidate, 'objective_ambiguous', 'critical', 'OA ambiguo', 'Más de un OA existente coincide con asignatura, curso, tipo y código.', ['matches' => $matches->pluck('id')->all()]);
            } else {
                $this->conflict($file, $candidate, 'objective_unmatched', 'warning', 'OA sin coincidencia exacta', 'Debe relacionarse manualmente o aprobarse como objetivo de una nueva versión.', ['code' => $official, 'type' => $type]);
            }
        }
    }

    private function conflict(CurriculumImportFile $file, ?CurriculumImportCandidate $candidate, string $type, string $severity, string $title, string $description, array $context): void
    {
        CurriculumImportConflict::query()->updateOrCreate(
            ['import_file_id' => $file->id, 'import_candidate_id' => $candidate?->id, 'conflict_type' => $type],
            ['severity' => $severity, 'status' => 'open', 'title' => $title, 'description' => $description, 'context' => $context],
        );
    }

    private function advance(CurriculumImportFile $file, string $stage, string $message): void
    {
        $progress = self::STAGES[$stage] ?? (int) $file->progress;
        $file->forceFill([
            'status' => $stage,
            'current_stage' => $stage,
            'progress' => $progress,
            'last_valid_stage_order' => $progress,
            'attempt_count' => $stage === 'validating' ? ((int) $file->attempt_count + 1) : $file->attempt_count,
            'processing_started_at' => $file->processing_started_at ?: now('UTC'),
        ])->save();
        $this->log($file, $stage, $message, 'info', $progress);
    }

    private function log(CurriculumImportFile $file, string $stage, string $message, string $level, int $progress, array $context = []): void
    {
        CurriculumImportLog::query()->create([
            'import_file_id' => $file->id,
            'stage' => $stage,
            'level' => $level,
            'message' => mb_substr($message, 0, 190),
            'context' => $context,
            'progress' => $progress,
            'occurred_at' => now('UTC'),
        ]);
    }

    private function refreshBatch(CurriculumImportBatch $batch): void
    {
        $files = $batch->files()->get(['status']);
        $status = match (true) {
            $files->contains('status', 'failed') && $files->every(fn (CurriculumImportFile $file): bool => in_array($file->status, CurriculumImportFile::TERMINAL_STATUSES, true)) => CurriculumImportBatch::STATUS_PENDING_REVIEW,
            $files->every(fn (CurriculumImportFile $file): bool => $file->status === 'published' || $file->status === 'published_with_warnings') => CurriculumImportBatch::STATUS_ACTIVATED,
            $files->every(fn (CurriculumImportFile $file): bool => in_array($file->status, CurriculumImportFile::TERMINAL_STATUSES, true)) => CurriculumImportBatch::STATUS_PENDING_REVIEW,
            default => CurriculumImportBatch::STATUS_PROCESSING,
        };
        $batch->forceFill([
            'status' => $status,
            'warning_count' => $batch->files()->whereNotNull('warnings')->count(),
            'error_count' => $batch->files()->where('status', 'failed')->count(),
            'completed_at' => $status !== CurriculumImportBatch::STATUS_PROCESSING ? now('UTC') : null,
        ])->save();
    }
}
