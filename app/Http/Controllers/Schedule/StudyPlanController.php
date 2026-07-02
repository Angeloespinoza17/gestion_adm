<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Schedule\StudyPlan;
use App\Services\Schedule\StudyPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudyPlanController extends Controller
{
    public function __construct(private readonly StudyPlanService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => StudyPlan::query()
                ->with(['academicYear:id,name,year,is_active', 'educationLevel:id,name', 'courseSection:id,display_name', 'subjects.scheduleSubject'])
                ->when($request->integer('academic_year_id'), fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
                ->when($request->integer('education_level_id'), fn ($query, $levelId) => $query->where('education_level_id', $levelId))
                ->when($request->integer('course_section_id'), fn ($query, $courseId) => $query->where('course_section_id', $courseId))
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Plan de estudio creado correctamente.',
            'data' => $this->service->createOrUpdate($this->validatedPayload($request)),
        ], 201);
    }

    public function update(Request $request, StudyPlan $studyPlan): JsonResponse
    {
        return response()->json([
            'message' => 'Plan de estudio actualizado correctamente.',
            'data' => $this->service->createOrUpdate($this->validatedPayload($request, $studyPlan), $studyPlan),
        ]);
    }

    public function storeSubject(Request $request, StudyPlan $studyPlan): JsonResponse
    {
        $planSubject = $this->service->assignSubjectHours($studyPlan, $this->validatedSubjectPayload($request));

        return response()->json([
            'message' => 'Horas de asignatura actualizadas en el plan.',
            'data' => $planSubject->fresh('scheduleSubject'),
        ], 201);
    }

    public function updateSubject(Request $request, StudyPlan $studyPlan, int $subjectId): JsonResponse
    {
        $payload = $this->validatedSubjectPayload($request, false);
        $payload['schedule_subject_id'] = $subjectId;
        $planSubject = $this->service->assignSubjectHours($studyPlan, $payload);

        return response()->json([
            'message' => 'Horas de asignatura actualizadas en el plan.',
            'data' => $planSubject->fresh('scheduleSubject'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?StudyPlan $studyPlan = null): array
    {
        return $request->validate([
            'academic_year_id' => [$studyPlan ? 'sometimes' : 'required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')],
            'course_section_id' => ['nullable', 'integer', Rule::exists('course_sections', 'id')],
            'name' => [$studyPlan ? 'sometimes' : 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'subjects' => ['sometimes', 'array'],
            'subjects.*.schedule_subject_id' => ['required_with:subjects', 'integer', Rule::exists('schedule_subjects', 'id')],
            'subjects.*.weekly_pedagogical_hours' => ['required_with:subjects', 'numeric', 'min:0', 'max:80'],
            'subjects.*.required' => ['sometimes', 'boolean'],
            'subjects.*.notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSubjectPayload(Request $request, bool $requireSubject = true): array
    {
        return $request->validate([
            'schedule_subject_id' => [$requireSubject ? 'required' : 'sometimes', 'integer', Rule::exists('schedule_subjects', 'id')],
            'weekly_pedagogical_hours' => ['required', 'numeric', 'min:0', 'max:80'],
            'required' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
