<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreEducationLevelRequest;
use App\Http\Requests\Students\UpdateEducationLevelRequest;
use App\Models\AcademicYear;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EducationLevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $academicYears = AcademicYear::query()
            ->ordered()
            ->get(['id', 'name', 'year', 'is_active']);
        $activeYear = $academicYears->firstWhere('is_active', true);
        $academicYearId = $request->query('academic_year_id') ?: $activeYear?->id;

        return response()->json([
            'data' => EducationLevel::query()
                ->select('education_levels.*')
                ->selectSub(
                    StudentEnrollment::query()
                        ->selectRaw('count(*)')
                        ->join('course_sections', 'course_sections.id', '=', 'student_enrollments.course_section_id')
                        ->whereColumn('course_sections.education_level_id', 'education_levels.id')
                        ->when($academicYearId, fn ($query) => $query->where('student_enrollments.academic_year_id', $academicYearId))
                        ->whereNotIn('student_enrollments.enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES),
                    'active_students_count'
                )
                ->withCount([
                    'courseSections as course_sections_count' => fn ($query) => $query
                        ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId)),
                ])
                ->orderBy('order')
                ->get(),
            'academic_years' => $academicYears,
            'active_academic_year_id' => $activeYear?->id,
            'selected_academic_year_id' => $academicYearId ? (int) $academicYearId : null,
            'type_options' => EducationLevel::TYPE_OPTIONS,
        ]);
    }

    public function store(StoreEducationLevelRequest $request): JsonResponse
    {
        $educationLevel = EducationLevel::query()->create($request->validated());

        return response()->json([
            'message' => 'Nivel creado correctamente.',
            'data' => $educationLevel->loadCount('courseSections'),
        ], 201);
    }

    public function update(UpdateEducationLevelRequest $request, EducationLevel $educationLevel): JsonResponse
    {
        $educationLevel->update($request->validated());

        return response()->json([
            'message' => 'Nivel actualizado correctamente.',
            'data' => $educationLevel->fresh()->loadCount('courseSections'),
        ]);
    }

    public function destroy(EducationLevel $educationLevel): JsonResponse
    {
        $courseCount = $educationLevel->courseSections()->count();

        if ($courseCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar este nivel porque ya tiene cursos asociados. Primero debe reasignar o eliminar esos cursos conservando la trazabilidad histórica.',
                'errors' => [
                    'education_level' => [
                        sprintf('El nivel tiene %d curso(s) asociado(s).', $courseCount),
                    ],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $educationLevel->delete();

        return response()->json([
            'message' => 'Nivel eliminado correctamente.',
        ]);
    }
}
