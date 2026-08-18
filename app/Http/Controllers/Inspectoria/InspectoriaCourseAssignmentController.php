<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspectoria\SaveInspectoriaAssignmentRequest;
use App\Http\Requests\Inspectoria\SaveInspectoriaBulkAssignmentRequest;
use App\Models\CourseSection;
use App\Models\Inspectoria\InspectoriaCourseAssignment;
use App\Services\Inspectoria\InspectoriaAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectoriaCourseAssignmentController extends Controller
{
    public function __construct(private readonly InspectoriaAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);

        $query = InspectoriaCourseAssignment::query()
            ->with(['academicYear:id,name,year,is_active', 'courseSection:id,display_name', 'inspector:id,full_name,rut']);
        if ($this->access->isCourseScoped($request->user())) {
            $query->where('inspector_staff_id', $request->user()->staff_id);
        }

        return response()->json($query
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->query('academic_year_id')))
            ->when($request->filled('inspector_staff_id'), fn ($q) => $q->where('inspector_staff_id', $request->query('inspector_staff_id')))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderByDesc('active')->latest('starts_on')->paginate((int) $request->query('per_page', 30)));
    }

    public function store(SaveInspectoriaAssignmentRequest $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::ASSIGNMENTS), 403);
        $payload = $request->validated();
        $this->validateAssignment($payload);
        $assignment = InspectoriaCourseAssignment::query()->create([
            ...$payload, 'active' => $payload['active'] ?? true,
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Curso asignado correctamente.', 'data' => $this->load($assignment)], 201);
    }

    public function bulkStore(SaveInspectoriaBulkAssignmentRequest $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::ASSIGNMENTS), 403);
        $payload = $request->validated();
        $courseIds = array_values(array_unique(array_map('intval', $payload['course_section_ids'])));
        $attributes = Arr::except($payload, ['course_section_ids']);

        $result = DB::transaction(function () use ($courseIds, $attributes, $request) {
            $courses = CourseSection::query()->whereIn('id', $courseIds)->get()->keyBy('id');
            $assignments = collect();
            $skipped = collect();

            foreach ($courseIds as $courseId) {
                $course = $courses->get($courseId);
                if ((int) $course?->academic_year_id !== (int) $attributes['academic_year_id']) {
                    throw ValidationException::withMessages([
                        'course_section_ids' => ($course?->display_name ?: "Curso #{$courseId}").': el curso no pertenece al año académico seleccionado.',
                    ]);
                }

                $existing = ($attributes['active'] ?? true)
                    ? $this->overlappingAssignment($courseId, $attributes)
                    : null;
                if ($existing) {
                    $skipped->push([
                        'course_section_id' => $courseId,
                        'course_name' => $course->display_name,
                        'assignment_id' => $existing->id,
                        'inspector_staff_id' => $existing->inspector_staff_id,
                    ]);

                    continue;
                }

                $assignments->push(InspectoriaCourseAssignment::query()->create([
                    ...$attributes,
                    'course_section_id' => $courseId,
                    'active' => $attributes['active'] ?? true,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]));
            }

            return ['assignments' => $assignments, 'skipped' => $skipped];
        });

        $assignments = $result['assignments'];
        $skipped = $result['skipped'];
        $createdCount = $assignments->count();
        $skippedCount = $skipped->count();

        return response()->json([
            'message' => $createdCount
                ? "{$createdCount} cursos asignados; {$skippedCount} ya estaban asignados a esta inspectora y fueron omitidos."
                : "Los {$skippedCount} cursos seleccionados ya estaban asignados a esta inspectora. No se generaron duplicados.",
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
            'skipped_courses' => $skipped->values(),
            'data' => $assignments->map(fn (InspectoriaCourseAssignment $assignment) => $this->load($assignment))->values(),
        ], $createdCount ? 201 : 200);
    }

    public function update(SaveInspectoriaAssignmentRequest $request, InspectoriaCourseAssignment $assignment): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::ASSIGNMENTS), 403);
        $payload = $request->validated();
        $this->validateAssignment($payload, $assignment->id);
        $assignment->fill($payload);
        $assignment->updated_by = $request->user()->id;
        $assignment->save();

        return response()->json(['message' => 'Asignación actualizada.', 'data' => $this->load($assignment)]);
    }

    public function destroy(Request $request, InspectoriaCourseAssignment $assignment): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::ASSIGNMENTS), 403);
        $assignment->forceFill(['active' => false, 'ends_on' => $assignment->ends_on ?? now()->toDateString(), 'updated_by' => $request->user()->id])->save();

        return response()->json(['message' => 'Asignación finalizada correctamente.']);
    }

    private function validateAssignment(array $payload, ?int $ignoreId = null): void
    {
        $course = CourseSection::query()->findOrFail($payload['course_section_id']);
        if ((int) $course->academic_year_id !== (int) $payload['academic_year_id']) {
            throw ValidationException::withMessages(['course_section_id' => 'El curso no pertenece al año académico seleccionado.']);
        }
        if (! ($payload['active'] ?? true)) {
            return;
        }
        if ($this->overlappingAssignment($course->id, $payload, $ignoreId)) {
            throw ValidationException::withMessages(['course_section_id' => 'La inspectora ya tiene este curso asignado en ese período.']);
        }
    }

    private function overlappingAssignment(int $courseId, array $payload, ?int $ignoreId = null): ?InspectoriaCourseAssignment
    {
        return InspectoriaCourseAssignment::query()
            ->where('course_section_id', $courseId)
            ->where('inspector_staff_id', $payload['inspector_staff_id'])
            ->where('active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('starts_on', '<=', $payload['ends_on'] ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $payload['starts_on']))
            ->first();
    }

    private function load(InspectoriaCourseAssignment $assignment): InspectoriaCourseAssignment
    {
        return $assignment->fresh(['academicYear:id,name,year,is_active', 'courseSection:id,display_name', 'inspector:id,full_name,rut']);
    }
}
