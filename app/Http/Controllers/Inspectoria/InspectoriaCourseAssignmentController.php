<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspectoria\SaveInspectoriaAssignmentRequest;
use App\Models\CourseSection;
use App\Models\Inspectoria\InspectoriaCourseAssignment;
use App\Services\Inspectoria\InspectoriaAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $overlap = InspectoriaCourseAssignment::query()->where('course_section_id', $course->id)->where('active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('starts_on', '<=', $payload['ends_on'] ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $payload['starts_on']))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['course_section_id' => 'El curso ya tiene una asignación vigente en ese período.']);
        }
    }

    private function load(InspectoriaCourseAssignment $assignment): InspectoriaCourseAssignment
    {
        return $assignment->fresh(['academicYear:id,name,year,is_active', 'courseSection:id,display_name', 'inspector:id,full_name,rut']);
    }
}
