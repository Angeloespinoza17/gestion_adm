<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Schedule\SchoolDayTemplate;
use App\Services\Schedule\JornadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolDayTemplateController extends Controller
{
    public function __construct(private readonly JornadaService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => SchoolDayTemplate::query()
                ->with(['academicYear:id,name,year,is_active', 'blocks'])
                ->withCount(['educationLevels', 'courseSections'])
                ->when($request->integer('academic_year_id'), fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $jornada = $this->service->create($this->validatedPayload($request));

        return response()->json([
            'message' => 'Jornada creada correctamente.',
            'data' => $jornada,
        ], 201);
    }

    public function show(SchoolDayTemplate $jornada): JsonResponse
    {
        return response()->json([
            'data' => $jornada->load(['academicYear:id,name,year,is_active', 'blocks', 'educationLevels:id,name', 'courseSections:id,display_name']),
        ]);
    }

    public function update(Request $request, SchoolDayTemplate $jornada): JsonResponse
    {
        return response()->json([
            'message' => 'Jornada actualizada correctamente.',
            'data' => $this->service->update($jornada, $this->validatedPayload($request, $jornada)),
        ]);
    }

    public function destroy(SchoolDayTemplate $jornada): JsonResponse
    {
        $jornada->delete();

        return response()->json(['message' => 'Jornada eliminada correctamente.']);
    }

    public function duplicate(Request $request, SchoolDayTemplate $jornada): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'message' => 'Jornada duplicada correctamente.',
            'data' => $this->service->duplicate($jornada->load('blocks'), $payload['name'] ?? null),
        ], 201);
    }

    public function assignLevels(Request $request, SchoolDayTemplate $jornada): JsonResponse
    {
        $payload = $request->validate([
            'level_ids' => ['required', 'array'],
            'level_ids.*' => ['integer', Rule::exists('education_levels', 'id')],
        ]);

        return response()->json([
            'message' => 'Jornada asignada a niveles correctamente.',
            'updated' => $this->service->assignToLevels($jornada, $payload['level_ids']),
        ]);
    }

    public function assignCourses(Request $request, SchoolDayTemplate $jornada): JsonResponse
    {
        $payload = $request->validate([
            'course_ids' => ['required', 'array'],
            'course_ids.*' => ['integer', Rule::exists('course_sections', 'id')],
        ]);

        return response()->json([
            'message' => 'Jornada asignada a cursos correctamente.',
            'updated' => $this->service->assignToCourses($jornada, $payload['course_ids']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?SchoolDayTemplate $jornada = null): array
    {
        return $request->validate([
            'academic_year_id' => [$jornada ? 'sometimes' : 'required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'name' => [$jornada ? 'sometimes' : 'required', 'string', 'max:255'],
            'start_time' => [$jornada ? 'sometimes' : 'required', 'date_format:H:i'],
            'end_time' => [$jornada ? 'sometimes' : 'required', 'date_format:H:i', 'after:start_time'],
            'days_of_week' => [$jornada ? 'sometimes' : 'required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'min:1', 'max:7'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'blocks' => ['sometimes', 'array'],
            'blocks.*.day_of_week' => ['required_with:blocks', 'integer', 'min:1', 'max:7'],
            'blocks.*.start_time' => ['required_with:blocks', 'date_format:H:i'],
            'blocks.*.end_time' => ['required_with:blocks', 'date_format:H:i'],
            'blocks.*.type' => ['required_with:blocks', Rule::in(['pedagogical_block', 'recess', 'lunch', 'non_assignable'])],
            'blocks.*.label' => ['nullable', 'string', 'max:255'],
            'blocks.*.order' => ['nullable', 'integer', 'min:1'],
            'blocks.*.assignable' => ['sometimes', 'boolean'],
            'blocks.*.pedagogical_hours_equivalent' => ['nullable', 'numeric', 'min:0', 'max:12'],
        ]);
    }
}
