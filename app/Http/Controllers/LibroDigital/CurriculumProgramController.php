<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\ResolveCurriculumProgramConflictRequest;
use App\Http\Requests\LibroDigital\ReviewCurriculumProgramCandidateRequest;
use App\Http\Requests\LibroDigital\StoreManualCurriculumProgramRequest;
use App\Http\Requests\LibroDigital\TransitionCurriculumProgramImportRequest;
use App\Http\Requests\LibroDigital\UploadCurriculumProgramDocumentsRequest;
use App\Jobs\ProcessCurriculumImportFile;
use App\Models\AcademicYear;
use App\Models\LibroDigital\CurriculumDocument;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportCandidate;
use App\Models\LibroDigital\CurriculumImportConflict;
use App\Models\LibroDigital\CurriculumImportFile;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\School;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\Curriculum\CurriculumManualProgramService;
use App\Services\LibroDigital\Curriculum\CurriculumPdfImportService;
use App\Services\LibroDigital\Curriculum\CurriculumProgramCatalogService;
use App\Services\LibroDigital\Curriculum\CurriculumProgramPublicationService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\LibroDigitalReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CurriculumProgramController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly CurriculumPdfImportService $imports,
        private readonly CurriculumManualProgramService $manualPrograms,
        private readonly CurriculumProgramPublicationService $publication,
        private readonly CurriculumProgramCatalogService $catalog,
        private readonly AuditEventWriter $audit,
        private readonly LibroDigitalReportService $reports,
    ) {
        parent::__construct($access);
    }

    public function matrix(Request $request): JsonResponse
    {
        $school = $this->school($request);
        $year = $this->academicYear($school, $request->integer('academic_year_id'));

        return $this->dataResponse($this->catalog->matrix($school->id, $year->id, $request));
    }

    public function index(Request $request): JsonResponse
    {
        $this->school($request);
        $programs = $this->catalog->query($request)->paginate(min(100, max(10, $request->integer('per_page', 25))));

        return $this->collectionResponse(
            $programs->getCollection()->map(fn (CurriculumProgram $program): array => $this->catalog->programSummary($program))->all(),
            ['current_page' => $programs->currentPage(), 'last_page' => $programs->lastPage(), 'per_page' => $programs->perPage(), 'total' => $programs->total()],
        );
    }

    public function showProgram(Request $request, string $program): JsonResponse
    {
        $this->school($request);

        return $this->dataResponse($this->catalog->detail($this->aggregate(CurriculumProgram::class, $program)));
    }

    public function createManual(StoreManualCurriculumProgramRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $year = $this->academicYear($school, $request->integer('academic_year_id'), requireOpen: true);
        if ($request->boolean('publish_now')) {
            $this->assertPermission($request, 'libro_digital.curriculum_programs.publish');
        }
        $program = $this->manualPrograms->create($school, $year, $request->user(), $request, $request->validated());

        return $this->dataResponse($this->catalog->detail($program), 201);
    }

    public function search(Request $request): JsonResponse
    {
        $this->school($request);

        return $this->collectionResponse($this->catalog->search($request));
    }

    public function imports(Request $request): JsonResponse
    {
        $school = $this->school($request);
        $year = $this->academicYear($school, $request->integer('academic_year_id'));
        $files = CurriculumImportFile::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)
            ->with(['uploader:id,name'])
            ->withCount(['candidates', 'conflicts as critical_conflicts_count' => fn (Builder $query) => $query->where('severity', 'critical')->where('status', 'open')])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')->paginate(min(100, max(10, $request->integer('per_page', 25))));

        return $this->collectionResponse($files->getCollection()->map(fn (CurriculumImportFile $file): array => [
            'id' => $file->public_id,
            'name' => $file->original_name,
            'status' => $file->status,
            'progress' => $file->progress,
            'stage' => $file->current_stage,
            'candidate_count' => $file->candidates_count,
            'critical_conflicts' => $file->critical_conflicts_count,
            'warnings_count' => count((array) $file->warnings),
            'uploaded_by' => $file->uploader?->name,
            'created_at' => $file->created_at?->toIso8601String(),
            'classification' => data_get($file->detected_metadata, 'classification'),
        ])->all(), ['current_page' => $files->currentPage(), 'last_page' => $files->lastPage(), 'per_page' => $files->perPage(), 'total' => $files->total()]);
    }

    public function upload(UploadCurriculumProgramDocumentsRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $year = $this->academicYear($school, $request->integer('academic_year_id'), requireOpen: true);
        $hints = collect($request->validated())->only([
            'schedule_subject_id', 'education_level_id', 'document_type', 'title', 'decree', 'edition',
            'publication_year', 'official_url', 'modality_code', 'formation_type_code',
        ])->filter(fn ($value): bool => filled($value))->all();
        $result = $this->imports->upload($request->file('files', []), $school, $year, $request->user(), $request, $hints);

        return $this->dataResponse([
            'batch_id' => $result['batch']?->public_id,
            'files' => collect($result['files'])->map(fn (CurriculumImportFile $file): array => ['id' => $file->public_id, 'name' => $file->original_name, 'status' => $file->status])->all(),
            'duplicates' => collect($result['duplicates'])->map(fn (CurriculumImportFile $file): array => ['id' => $file->public_id, 'name' => $file->original_name, 'status' => $file->status, 'sha256' => $file->sha256])->all(),
        ], $result['batch'] ? 202 : 200);
    }

    public function showImport(Request $request, string $file): JsonResponse
    {
        return $this->dataResponse($this->catalog->importDetail($this->fileForRequest($request, $file)));
    }

    public function reviewCandidate(ReviewCurriculumProgramCandidateRequest $request, string $candidate): JsonResponse
    {
        /** @var CurriculumImportCandidate $model */
        $model = $this->aggregate(CurriculumImportCandidate::class, $candidate);
        $this->fileForRequest($request, (string) $model->import_file_id);
        $updated = $this->publication->reviewCandidate($model, $request->user(), $request->validated(), $request);

        return $this->dataResponse(['id' => $updated->public_id, 'review_status' => $updated->review_status, 'suggested_action' => $updated->suggested_action]);
    }

    public function resolveConflict(ResolveCurriculumProgramConflictRequest $request, string $conflict): JsonResponse
    {
        /** @var CurriculumImportConflict $model */
        $model = $this->aggregate(CurriculumImportConflict::class, $conflict);
        $this->fileForRequest($request, (string) $model->import_file_id);
        $updated = $this->publication->resolveConflict($model, $request->user(), $request->string('resolution')->toString(), $request);

        return $this->dataResponse(['id' => $updated->public_id, 'status' => $updated->status, 'resolution' => $updated->resolution]);
    }

    public function validateImport(TransitionCurriculumProgramImportRequest $request, string $file): JsonResponse
    {
        $this->assertPermission($request, 'libro_digital.curriculum_programs.review');
        $model = $this->publication->validate($this->fileForRequest($request, $file), $request->user(), $request->input('note'), $request);

        return $this->dataResponse($this->catalog->importDetail($model));
    }

    public function publishImport(TransitionCurriculumProgramImportRequest $request, string $file): JsonResponse
    {
        $this->assertPermission($request, 'libro_digital.curriculum_programs.publish');
        $program = $this->publication->publish($this->fileForRequest($request, $file), $request->user(), $request->input('note'), $request);

        return $this->dataResponse($this->catalog->detail($program), 201);
    }

    public function publishBatch(TransitionCurriculumProgramImportRequest $request, string $batch): JsonResponse
    {
        $this->assertPermission($request, 'libro_digital.curriculum_programs.publish');
        $school = $this->school($request);
        /** @var CurriculumImportBatch $model */
        $model = $this->aggregate(CurriculumImportBatch::class, $batch);
        abort_unless((int) $model->school_id === (int) $school->id, 403);
        $published = [];
        $errors = [];
        foreach ($model->files()->where('status', 'validated')->get() as $file) {
            try {
                $program = $this->publication->publish($file, $request->user(), $request->input('note'), $request);
                $published[] = $this->catalog->programSummary($program);
            } catch (\Throwable $exception) {
                $errors[] = ['file_id' => $file->public_id, 'message' => $exception->getMessage()];
            }
        }

        return $this->dataResponse(['published' => $published, 'errors' => $errors], $errors === [] ? 200 : 207);
    }

    public function reprocess(TransitionCurriculumProgramImportRequest $request, string $file): JsonResponse
    {
        $this->assertPermission($request, 'libro_digital.curriculum_programs.reprocess');
        $model = $this->fileForRequest($request, $file);
        if (in_array($model->status, ['published', 'published_with_warnings', 'archived'], true)) {
            throw new LibroDigitalException('Una publicación o archivo histórico no se reprocesa en el mismo registro.', 'LCD_CURRICULUM_IMPORT_REPROCESS_UNSAFE', 409);
        }
        $before = ['status' => $model->status, 'progress' => $model->progress];
        $model->forceFill(['status' => 'uploaded', 'current_stage' => 'uploaded', 'progress' => 0, 'failed_at' => null, 'error_summary' => null, 'cancel_requested_at' => null])->save();
        ProcessCurriculumImportFile::dispatch($model->id, true)
            ->onQueue((string) config('libro_digital.curriculum_import.queue', 'curriculum-imports'));
        $this->audit->write('curriculum.import.reprocessed', 'reprocess', $model, actor: $request->user(), schoolId: $model->school_id, academicYearId: $model->academic_year_id, before: $before, after: ['status' => 'uploaded', 'progress' => 0], reason: $request->input('note'), request: $request);

        return $this->dataResponse(['id' => $model->public_id, 'status' => 'uploaded'], 202);
    }

    public function archiveImport(TransitionCurriculumProgramImportRequest $request, string $file): JsonResponse
    {
        $this->assertPermission($request, 'libro_digital.curriculum_programs.archive');
        $model = $this->fileForRequest($request, $file);
        if (in_array($model->status, ['published', 'published_with_warnings'], true)) {
            throw new LibroDigitalException('Archiva el programa publicado solo mediante la reversión segura.', 'LCD_CURRICULUM_IMPORT_ALREADY_PUBLISHED', 409);
        }
        $before = ['status' => $model->status, 'stage' => $model->current_stage];
        $model->forceFill(['status' => 'archived', 'current_stage' => 'archived', 'cancel_requested_at' => now('UTC')])->save();
        $this->audit->write('curriculum.import.archived', 'archive', $model, actor: $request->user(), schoolId: $model->school_id, academicYearId: $model->academic_year_id, before: $before, after: ['status' => 'archived', 'stage' => 'archived'], reason: $request->input('note'), request: $request);

        return $this->dataResponse(['id' => $model->public_id, 'status' => 'archived']);
    }

    public function archiveProgram(TransitionCurriculumProgramImportRequest $request, string $program): JsonResponse
    {
        $this->assertPermission($request, 'libro_digital.curriculum_programs.archive');
        $school = $this->school($request);
        /** @var CurriculumProgram $model */
        $model = $this->aggregate(CurriculumProgram::class, $program);
        $used = DB::table('lcd_class_sessions')->where('curriculum_program_id', $model->id)->exists()
            || DB::table('lcd_assessments')->where('curriculum_program_id', $model->id)->exists();
        if ($used) {
            throw new LibroDigitalException('El programa tiene clases o evaluaciones asociadas y debe conservarse publicado.', 'LCD_CURRICULUM_PROGRAM_IN_USE', 409);
        }
        $before = ['status' => $model->status];
        $model->forceFill(['status' => 'archived', 'revision' => (int) $model->revision + 1])->save();
        $this->audit->write('curriculum.program.archived', 'archive', $model, actor: $request->user(), schoolId: $school->id, academicYearId: $request->integer('academic_year_id') ?: null, before: $before, after: ['status' => 'archived'], reason: $request->input('note'), request: $request, entityRevision: $model->revision);

        return $this->dataResponse($this->catalog->detail($model));
    }

    public function exportPdf(TransitionCurriculumProgramImportRequest $request, string $program): JsonResponse
    {
        $this->assertPermission($request, 'libro_digital.curriculum_programs.export_pdf');
        $school = $this->school($request);
        $year = $this->academicYear($school, $request->integer('academic_year_id'));
        /** @var CurriculumProgram $model */
        $model = $this->aggregate(CurriculumProgram::class, $program);
        $export = $this->reports->request($school, $request->user(), 'curriculum_program', 'pdf', array_filter([
            'academic_year_id' => $year->id,
            'program_id' => $model->public_id,
            'unit_id' => $request->input('unit_id'),
        ], fn ($value): bool => filled($value)));

        return $this->dataResponse([
            'id' => $export->public_id,
            'status' => $export->status,
            'progress' => $export->progress,
            'report_type' => $export->report_type,
            'expires_at' => $export->expires_at?->toIso8601String(),
        ], 202);
    }

    public function documentPage(Request $request, string $document, int $page): JsonResponse
    {
        $this->school($request);
        /** @var CurriculumDocument $model */
        $model = $this->aggregate(CurriculumDocument::class, $document);
        $source = $model->pages()->where('physical_page_number', $page)->firstOrFail();

        return $this->dataResponse([
            'document_id' => $model->public_id,
            'physical_page' => $source->physical_page_number,
            'printed_page' => $source->printed_page_label,
            'text' => $source->normalized_text,
            'ocr_text' => $source->ocr_text,
            'method' => $source->extraction_method,
            'confidence' => (float) $source->confidence,
            'warnings' => $source->processing_warnings ?? [],
            'has_previous' => $page > 1,
            'has_next' => $page < $model->page_count,
        ]);
    }

    public function downloadDocument(Request $request, string $document): Response
    {
        $this->school($request);
        /** @var CurriculumDocument $model */
        $model = $this->aggregate(CurriculumDocument::class, $document);
        $encrypted = Storage::disk($model->disk)->get($model->storage_path);
        $contents = Crypt::decryptString($encrypted);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace(['"', "\r", "\n"], '', $model->original_filename).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function fileForRequest(Request $request, string $identifier): CurriculumImportFile
    {
        $school = $this->school($request);
        /** @var CurriculumImportFile $file */
        $file = $this->aggregate(CurriculumImportFile::class, $identifier);
        abort_unless((int) $file->school_id === (int) $school->id, 403);

        return $file;
    }

    private function academicYear(School $school, int $academicYearId, bool $requireOpen = false): AcademicYear
    {
        $year = $school->academicYears()->where('academic_years.id', $academicYearId)->wherePivot('active', true)->first();
        if (! $year) {
            throw new LibroDigitalException('El año académico no está habilitado para el establecimiento.', 'LCD_CURRICULUM_SCHOOL_YEAR_REQUIRED', 422);
        }
        if ($requireOpen && $year->is_closed) {
            throw new LibroDigitalException('No se importan programas en un año académico cerrado.', 'LCD_CURRICULUM_YEAR_CLOSED', 409);
        }

        return $year;
    }

    private function assertPermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
