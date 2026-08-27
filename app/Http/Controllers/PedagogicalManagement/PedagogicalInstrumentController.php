<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\ListPedagogicalInstrumentsRequest;
use App\Http\Requests\PedagogicalManagement\StorePedagogicalInstrumentRequest;
use App\Http\Requests\PedagogicalManagement\UpdatePedagogicalInstrumentRequest;
use App\Http\Resources\PedagogicalManagement\PedagogicalInstrumentResource;
use App\Models\CourseSection;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\LibroDigital\LearningObjective;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAccessService;
use App\Services\PedagogicalManagement\PedagogicalInstrumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PedagogicalInstrumentController extends Controller
{
    public function index(ListPedagogicalInstrumentsRequest $request, PedagogicalInstrumentAccessService $access): JsonResponse
    {
        $filters = $request->validated();
        $query = $access->visibleQuery($request->user())
            ->when(($filters['scope'] ?? null) === 'mine', fn (Builder $q) => $q->where('owner_user_id', $request->user()->id))
            ->with([
                'school:id,name,rbd', 'academicYear:id,name,year', 'owner:id,name', 'subject:id,name,code,color',
                'courses:id,display_name,education_level_id', 'latestFile', 'latestAnalysisRun.validationResults',
                'latestReview.instrument:id,owner_user_id,school_id,academic_year_id', 'latestReview.reviewer:id,name',
                'latestReview.instrumentFile', 'latestReview.guidanceDocuments', 'latestReview.aiReport.instrumentFile',
                'latestAiReport.instrumentFile', 'latestAiReport.requester:id,name',
            ])
            ->when($filters['school_id'] ?? null, fn (Builder $q, $value) => $q->where('school_id', $value))
            ->when($filters['academic_year_id'] ?? null, fn (Builder $q, $value) => $q->where('academic_year_id', $value))
            ->when($filters['subject_id'] ?? null, fn (Builder $q, $value) => $q->where('subject_id', $value))
            ->when($filters['owner_user_id'] ?? null, fn (Builder $q, $value) => $q->where('owner_user_id', $value))
            ->when($filters['instrument_type'] ?? null, fn (Builder $q, $value) => $q->where('instrument_type', $value))
            ->when($filters['evaluation_purpose'] ?? null, fn (Builder $q, $value) => $q->where('evaluation_purpose', $value))
            ->when($filters['status'] ?? null, fn (Builder $q, $value) => $q->where('status', $value))
            ->when($filters['workflow_status'] ?? null, fn (Builder $q, $value) => $q->where('workflow_status', $value))
            ->when($filters['from'] ?? null, fn (Builder $q, $value) => $q->whereDate('application_date', '>=', $value))
            ->when($filters['to'] ?? null, fn (Builder $q, $value) => $q->whereDate('application_date', '<=', $value))
            ->when($filters['search'] ?? null, fn (Builder $q, $value) => $q->where(fn (Builder $nested) => $nested
                ->where('title', 'like', '%'.$value.'%')->orWhere('grade_label', 'like', '%'.$value.'%')));
        $statusCounts = (clone $query)->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $paginator = $query->latest('id')->paginate((int) ($filters['per_page'] ?? config('pedagogical_management.pagination.instruments', 15)));

        return response()->json([
            'data' => PedagogicalInstrumentResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'status_counts' => $statusCounts,
            ],
        ]);
    }

    public function store(
        StorePedagogicalInstrumentRequest $request,
        PedagogicalInstrumentAccessService $access,
        PedagogicalInstrumentService $service,
    ): JsonResponse {
        $this->authorize('create', PedagogicalInstrument::class);
        $data = $request->validated();
        if (! $request->user()->hasPermission('pedagogical-instruments.view-all')) {
            $data['owner_user_id'] = $request->user()->id;
        }
        $school = $access->resolveSchool($request);
        $course = CourseSection::query()->whereKey($data['course_id'])->where('active', true)->firstOrFail();
        $data = [
            'academic_year_id' => $course->academic_year_id,
            'owner_user_id' => $data['owner_user_id'],
            'subject_id' => $data['subject_id'],
            'title' => $this->titleFromUpload($request->instrumentFile()->getClientOriginalName()),
            'grade_label' => $course->display_name,
            'instrument_type' => 'other',
            'evaluation_purpose' => 'other',
            'work_modality' => 'individual',
            'course_ids' => [$course->id],
        ];
        $this->assertCatalogConsistency($data, $school->id, $request->user()->id);
        [$instrument, $file] = $service->create(Arr::except($data, ['file', 'pdf', 'school_id']), $request->instrumentFile(), $school, $request->user(), $request);
        return response()->json(['data' => [
            'instrument' => new PedagogicalInstrumentResource($this->detailQuery($instrument)),
        ]], 201);
    }

    public function show(PedagogicalInstrument $instrument): PedagogicalInstrumentResource
    {
        $this->authorize('view', $instrument);

        return new PedagogicalInstrumentResource($this->detailQuery($instrument));
    }

    public function update(
        UpdatePedagogicalInstrumentRequest $request,
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentService $service,
    ): JsonResponse {
        $data = $request->validated();
        if (! $request->user()->hasPermission('pedagogical-instruments.view-all')) {
            $data['owner_user_id'] = $instrument->owner_user_id;
        }
        $this->assertCatalogConsistency([...$instrument->toArray(), ...$data], (int) $instrument->school_id, $request->user()->id);
        $updated = $service->update($instrument, Arr::except($data, ['school_id']), $request->user(), $request);
        return response()->json(['data' => [
            'instrument' => new PedagogicalInstrumentResource($this->detailQuery($updated)),
        ]]);
    }

    public function archive(PedagogicalInstrument $instrument, PedagogicalInstrumentService $service): JsonResponse
    {
        $this->authorize('archive', $instrument);
        $service->archive($instrument, request()->user(), request());

        return response()->json(['message' => 'Instrumento archivado sin eliminar su ficha, archivo original ni trazabilidad.']);
    }

    private function detailQuery(PedagogicalInstrument $instrument): PedagogicalInstrument
    {
        return $instrument->fresh()->load([
            'school:id,name,rbd', 'academicYear:id,name,year', 'owner:id,name', 'subject:id,name,code,color',
            'courses:id,display_name,education_level_id', 'files.uploader:id,name', 'latestFile', 'latestAnalysisRun.validationResults',
            'reviews.instrument:id,owner_user_id,school_id,academic_year_id', 'reviews.reviewer:id,name',
            'reviews.instrumentFile', 'reviews.guidanceDocuments', 'reviews.aiReport.instrumentFile', 'reviews.aiReport.requester:id,name',
            'latestReview.instrument:id,owner_user_id,school_id,academic_year_id', 'latestReview.reviewer:id,name',
            'latestReview.instrumentFile', 'latestReview.guidanceDocuments', 'latestReview.aiReport.instrumentFile',
            'latestAiReport.instrumentFile', 'latestAiReport.requester:id,name',
        ]);
    }

    private function titleFromUpload(string $filename): string
    {
        $title = trim((string) Str::of(pathinfo($filename, PATHINFO_FILENAME))->replace(['_', '-'], ' ')->squish());

        return Str::limit($title !== '' ? $title : 'Instrumento pedagógico', 191, '');
    }

    /** @param array<string,mixed> $data */
    private function assertCatalogConsistency(array $data, int $schoolId, int $actorId): void
    {
        $errors = [];
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if (! \DB::table('lcd_school_academic_years')->where('school_id', $schoolId)->where('academic_year_id', $yearId)->where('active', true)->exists()) {
            $errors['academic_year_id'] = 'El año académico no está habilitado para el establecimiento.';
        }
        $courseIds = array_map('intval', (array) ($data['course_ids'] ?? []));
        if ($courseIds !== [] && CourseSection::query()->whereIn('id', $courseIds)->where('academic_year_id', $yearId)->count() !== count(array_unique($courseIds))) {
            $errors['course_ids'] = 'Todos los cursos deben pertenecer al año académico seleccionado.';
        }
        $subjectId = (int) ($data['subject_id'] ?? 0);
        $unitId = (int) ($data['unit_id'] ?? 0);
        if ($unitId && ! CurriculumUnit::query()->whereKey($unitId)->whereHas('program', fn (Builder $query) => $query->where('schedule_subject_id', $subjectId))->exists()) {
            $errors['unit_id'] = 'La unidad no pertenece a la asignatura seleccionada.';
        }
        $objectiveIds = array_map('intval', (array) ($data['learning_objective_ids'] ?? []));
        if ($objectiveIds !== [] && LearningObjective::query()->whereIn('id', $objectiveIds)->where('active', true)
            ->where(fn (Builder $query) => $query->whereNull('schedule_subject_id')->orWhere('schedule_subject_id', $subjectId))->count() !== count(array_unique($objectiveIds))) {
            $errors['learning_objective_ids'] = 'Todos los OA deben ser activos y corresponder a la asignatura seleccionada.';
        }
        $ownerId = (int) ($data['owner_user_id'] ?? $actorId);
        if ($ownerId !== $actorId && ! \DB::table('lcd_school_users')->where('school_id', $schoolId)->where('user_id', $ownerId)->where('active', true)->exists()) {
            $errors['owner_user_id'] = 'El responsable no tiene una vinculación activa con el establecimiento.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
