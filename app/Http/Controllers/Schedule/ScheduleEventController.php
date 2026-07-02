<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Schedule\ScheduleEvent;
use App\Services\Schedule\ScheduleEventService;
use App\Services\Schedule\ScheduleValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleEventController extends Controller
{
    public function __construct(
        private readonly ScheduleEventService $service,
        private readonly ScheduleValidationService $validationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ScheduleEvent::query()
                ->with([
                    'teacher:id,full_name,institutional_email',
                    'layer:id,name,type,color,visible_by_default,priority,active',
                    'courseSection:id,display_name,academic_year_id,education_level_id,school_day_template_id',
                    'courseSection.educationLevel:id,name,default_school_day_template_id',
                    'subject:id,name,code,color,area',
                    'schoolDayTemplate:id,name,start_time,end_time,days_of_week',
                    'schoolDayBlock:id,label,type,assignable,start_time,end_time',
                    'validationIssues' => fn ($query) => $query->where('resolved', false),
                ])
                ->when($request->integer('academic_year_id'), fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
                ->when($request->integer('teacher_id'), fn ($query, $teacherId) => $query->where('staff_id', $teacherId))
                ->when($request->integer('course_section_id'), fn ($query, $courseId) => $query->where('course_section_id', $courseId))
                ->when($request->integer('schedule_subject_id'), fn ($query, $subjectId) => $query->where('schedule_subject_id', $subjectId))
                ->when($request->integer('layer_id'), fn ($query, $layerId) => $query->where('teacher_schedule_layer_id', $layerId))
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request);
        $force = $this->canForce($request);

        return response()->json([
            'message' => 'Bloque de horario creado correctamente.',
            'data' => $this->service->create($payload, $force),
        ], 201);
    }

    public function update(Request $request, ScheduleEvent $event): JsonResponse
    {
        $force = $this->canForce($request);

        return response()->json([
            'message' => 'Bloque de horario actualizado correctamente.',
            'data' => $this->service->update($event, $this->validatedPayload($request, $event), $force),
        ]);
    }

    public function destroy(ScheduleEvent $event): JsonResponse
    {
        $this->service->delete($event);

        return response()->json(['message' => 'Bloque de horario eliminado correctamente.']);
    }

    public function move(Request $request, ScheduleEvent $event): JsonResponse
    {
        $payload = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'school_day_block_id' => ['nullable', 'integer', Rule::exists('school_day_blocks', 'id')],
        ]);

        return response()->json([
            'message' => 'Bloque movido correctamente.',
            'data' => $this->service->move($event, $payload, $this->canForce($request)),
        ]);
    }

    public function validateEvent(ScheduleEvent $event): JsonResponse
    {
        $issues = $this->service->syncValidationIssues($event);

        return response()->json([
            'data' => $issues,
            'has_errors' => collect($issues)->contains(fn (array $issue) => $issue['severity'] === 'error'),
        ]);
    }

    public function previewValidation(Request $request): JsonResponse
    {
        $issues = $this->validationService->validateScheduleEvent($this->validatedPayload($request));

        return response()->json([
            'data' => $issues,
            'has_errors' => collect($issues)->contains(fn (array $issue) => $issue['severity'] === 'error'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?ScheduleEvent $event = null): array
    {
        return $request->validate([
            'academic_year_id' => [$event ? 'sometimes' : 'required', 'integer', Rule::exists('academic_years', 'id')],
            'staff_id' => [$event ? 'sometimes' : 'required', 'integer', Rule::exists('staff', 'id')],
            'teacher_schedule_layer_id' => [$event ? 'sometimes' : 'required', 'integer', Rule::exists('teacher_schedule_layers', 'id')],
            'course_section_id' => ['nullable', 'integer', Rule::exists('course_sections', 'id')],
            'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')],
            'schedule_subject_id' => ['nullable', 'integer', Rule::exists('schedule_subjects', 'id')],
            'school_day_template_id' => ['nullable', 'integer', Rule::exists('school_day_templates', 'id')],
            'school_day_block_id' => ['nullable', 'integer', Rule::exists('school_day_blocks', 'id')],
            'day_of_week' => [$event ? 'sometimes' : 'required', 'integer', 'min:1', 'max:7'],
            'start_time' => [$event ? 'sometimes' : 'required', 'date_format:H:i'],
            'end_time' => [$event ? 'sometimes' : 'required', 'date_format:H:i'],
            'activity_type' => [$event ? 'sometimes' : 'required', Rule::in(['lective_class', 'non_lective', 'meeting', 'coordination', 'extracurricular', 'pie', 'replacement', 'workshop', 'availability_block', 'class', 'jefatura_course'])],
            'pedagogical_hours' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'room_id' => ['nullable', 'integer'],
            'room_name' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['draft', 'confirmed', 'conflict', 'blocked'])],
            'source' => ['sometimes', Rule::in(['manual', 'imported', 'generated'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function canForce(Request $request): bool
    {
        if (!$request->boolean('force_validation_exception')) {
            return false;
        }

        abort_unless(
            $request->user()?->hasPermission('forzar_excepciones_horario'),
            403,
            'No tienes permiso para forzar excepciones de horario.',
        );

        return true;
    }
}
