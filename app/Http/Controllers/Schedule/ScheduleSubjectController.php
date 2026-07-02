<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleSubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ScheduleSubject::query()
                ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $subject = ScheduleSubject::query()->create($this->validatedPayload($request));

        return response()->json([
            'message' => 'Asignatura creada correctamente.',
            'data' => $subject,
        ], 201);
    }

    public function update(Request $request, ScheduleSubject $subject): JsonResponse
    {
        $subject->update($this->validatedPayload($request, $subject));

        return response()->json([
            'message' => 'Asignatura actualizada correctamente.',
            'data' => $subject->fresh(),
        ]);
    }

    public function destroy(ScheduleSubject $subject): JsonResponse
    {
        if ($subject->studyPlanSubjects()->exists() || $subject->scheduleEvents()->exists()) {
            $subject->update(['active' => false]);

            return response()->json([
                'message' => 'La asignatura tiene historial. Fue desactivada para conservar trazabilidad.',
                'data' => $subject->fresh(),
            ]);
        }

        $subject->delete();

        return response()->json(['message' => 'Asignatura eliminada correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?ScheduleSubject $subject = null): array
    {
        return $request->validate([
            'name' => [$subject ? 'sometimes' : 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('schedule_subjects', 'code')->ignore($subject?->id)],
            'color' => ['sometimes', 'string', 'max:20'],
            'area' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
