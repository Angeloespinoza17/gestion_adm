<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Schedule\TeacherScheduleLayer;
use App\Models\Staff;
use App\Services\Schedule\ScheduleLayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherScheduleLayerController extends Controller
{
    public function __construct(private readonly ScheduleLayerService $service)
    {
    }

    public function index(Request $request, Staff $teacher): JsonResponse
    {
        return response()->json([
            'data' => TeacherScheduleLayer::query()
                ->where('staff_id', $teacher->id)
                ->when($request->integer('academic_year_id'), fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
                ->orderBy('priority')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Staff $teacher): JsonResponse
    {
        $payload = $this->validatedPayload($request);
        $payload['staff_id'] = $teacher->id;

        return response()->json([
            'message' => 'Capa de horario creada correctamente.',
            'data' => $this->service->createOrUpdate($payload),
        ], 201);
    }

    public function update(Request $request, TeacherScheduleLayer $layer): JsonResponse
    {
        return response()->json([
            'message' => 'Capa de horario actualizada correctamente.',
            'data' => $this->service->createOrUpdate($this->validatedPayload($request, $layer), $layer),
        ]);
    }

    public function destroy(TeacherScheduleLayer $layer): JsonResponse
    {
        if ($layer->events()->exists()) {
            $layer->update(['active' => false]);

            return response()->json([
                'message' => 'La capa tiene eventos asociados. Fue desactivada para conservar trazabilidad.',
                'data' => $layer->fresh(),
            ]);
        }

        $layer->delete();

        return response()->json(['message' => 'Capa eliminada correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?TeacherScheduleLayer $layer = null): array
    {
        return $request->validate([
            'academic_year_id' => [$layer ? 'sometimes' : 'required', 'integer', Rule::exists('academic_years', 'id')],
            'name' => [$layer ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['lective', 'non_lective', 'extracurricular', 'coordination', 'meeting', 'pie', 'replacement', 'workshop', 'availability_block', 'other'])],
            'color' => ['sometimes', 'string', 'max:20'],
            'visible_by_default' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
