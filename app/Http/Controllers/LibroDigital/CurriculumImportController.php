<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\ActivateCurriculumImportRequest;
use App\Http\Requests\LibroDigital\ApproveCurriculumImportRequest;
use App\Http\Requests\LibroDigital\ListCurriculumImportsRequest;
use App\Http\Requests\LibroDigital\ValidateCurriculumImportRequest;
use App\Http\Resources\LibroDigital\CurriculumImportResource;
use App\Models\AcademicYear;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\School;
use App\Services\LibroDigital\CurriculumImportService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CurriculumImportController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly CurriculumImportService $imports,
    ) {
        parent::__construct($access);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $this->assertCanViewImports($request);
        $path = resource_path('templates/libro-digital/plantilla-importacion-curriculo-nt1-4m.xlsx');
        if (! is_file($path) || ! is_readable($path)) {
            throw new LibroDigitalException(
                'La plantilla curricular no está disponible en esta instalación.',
                'LCD_CURRICULUM_TEMPLATE_MISSING',
                404,
            );
        }

        return response()->download(
            $path,
            'plantilla-importacion-curriculo-nt1-4m.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function index(ListCurriculumImportsRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $batches = CurriculumImportBatch::query()
            ->where('school_id', $school->id)
            ->with([
                'curriculumCatalog:id,public_id,code,name,version,source_hash,active',
                'requester:id,name',
                'activations' => fn ($query) => $query->latest('id'),
            ])
            ->withCount('evidences')
            ->when($request->filled('academic_year_id'), fn (Builder $query) => $query
                ->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query
                ->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return $this->collectionResponse(
            $batches->getCollection()
                ->map(fn (CurriculumImportBatch $batch): array => $this->payload($batch, $request))
                ->all(),
            [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        );
    }

    public function validateUpload(ValidateCurriculumImportRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $academicYear = $this->academicYear($school, $request->integer('academic_year_id'));
        $file = $request->file('file');
        if (! $file) {
            throw new LibroDigitalException('Selecciona el archivo XLSX que deseas validar.', 'LCD_CURRICULUM_FILE_REQUIRED');
        }

        $result = $this->imports->validateUpload(
            $file,
            $school,
            $academicYear,
            $request->user(),
            $request,
        );
        $batch = $this->loadResourceRelations($result['batch']);
        $payload = $this->payload($batch, $request);
        $payload['created'] = (bool) $result['created'];

        return $this->dataResponse(
            $payload,
            $result['created'] ? 201 : 200,
            max(1, (int) $batch->lock_version),
        );
    }

    public function show(Request $request, string $import): JsonResponse
    {
        $this->assertCanViewImports($request);
        $batch = $this->loadResourceRelations($this->importForRequest($request, $import), includeEvidences: true);

        return $this->dataResponse(
            $this->payload($batch, $request),
            version: max(1, (int) $batch->lock_version),
        );
    }

    public function approve(ApproveCurriculumImportRequest $request, string $import): JsonResponse
    {
        $batch = $this->importForRequest($request, $import);
        $this->assertImportYearOpen($batch);
        $approved = $this->imports->approve(
            $batch,
            $request->user(),
            $request->string('note')->toString(),
            $request->integer('lock_version'),
            $request,
        );
        $approved = $this->loadResourceRelations($approved, includeEvidences: true);

        return $this->dataResponse(
            $this->payload($approved, $request),
            version: max(1, (int) $approved->lock_version),
        );
    }

    public function activate(ActivateCurriculumImportRequest $request, string $import): JsonResponse
    {
        $batch = $this->importForRequest($request, $import);
        $this->assertImportYearOpen($batch);
        $activated = $this->imports->activate(
            $batch,
            $request->user(),
            $request->string('note')->toString(),
            $request->integer('lock_version'),
            $request,
        );
        $activated = $this->loadResourceRelations($activated, includeEvidences: true);

        return $this->dataResponse(
            $this->payload($activated, $request),
            version: max(1, (int) $activated->lock_version),
        );
    }

    private function academicYear(School $school, int $academicYearId): AcademicYear
    {
        /** @var AcademicYear|null $academicYear */
        $academicYear = $school->academicYears()
            ->where('academic_years.id', $academicYearId)
            ->wherePivot('active', true)
            ->first();
        if (! $academicYear) {
            throw new LibroDigitalException(
                'El año académico no está habilitado para el establecimiento seleccionado.',
                'LCD_CURRICULUM_SCHOOL_YEAR_REQUIRED',
                422,
            );
        }
        if ($academicYear->is_closed) {
            throw new LibroDigitalException(
                'No se importan ni activan versiones curriculares retroactivamente en un año académico cerrado.',
                'LCD_CURRICULUM_YEAR_CLOSED',
                409,
            );
        }

        return $academicYear;
    }

    private function importForRequest(Request $request, string $identifier): CurriculumImportBatch
    {
        /** @var CurriculumImportBatch $batch */
        $batch = $this->aggregate(CurriculumImportBatch::class, $identifier);
        $user = $request->user();
        if (! $user
            || ! School::query()->whereKey($batch->school_id)->where('active', true)->exists()
            || ! $this->access->canAccessSchool($user, (int) $batch->school_id)) {
            abort(403);
        }

        $selectedSchoolId = $request->integer('school_id') ?: (int) $request->header('X-LCD-School-ID');
        if ($selectedSchoolId && $selectedSchoolId !== (int) $batch->school_id) {
            throw new LibroDigitalException(
                'La importación no pertenece al establecimiento seleccionado.',
                'LCD_CURRICULUM_IMPORT_SCOPE_INVALID',
                403,
            );
        }

        return $batch;
    }

    private function assertImportYearOpen(CurriculumImportBatch $batch): void
    {
        $school = School::query()->whereKey($batch->school_id)->where('active', true)->firstOrFail();
        $this->academicYear($school, (int) $batch->academic_year_id);
    }

    private function loadResourceRelations(
        CurriculumImportBatch $batch,
        bool $includeEvidences = false,
    ): CurriculumImportBatch {
        $relations = [
            'curriculumCatalog:id,public_id,code,name,version,source_hash,active',
            'requester:id,name',
            'activations' => fn ($query) => $query->latest('id'),
        ];
        if ($includeEvidences) {
            $relations[] = 'evidences';
        }

        return $batch->load($relations)->loadCount('evidences');
    }

    /** @return array<string, mixed> */
    private function payload(CurriculumImportBatch $batch, Request $request): array
    {
        return (new CurriculumImportResource($batch))->resolve($request);
    }

    private function assertCanViewImports(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && collect([
            'libro_digital.curriculum.import',
            'libro_digital.curriculum.approve',
            'libro_digital.curriculum.activate',
            'libro_digital.audit.view',
        ])->contains(fn (string $permission): bool => $user->hasPermission($permission)), 403);
    }
}
