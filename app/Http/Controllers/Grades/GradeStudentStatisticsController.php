<?php

namespace App\Http\Controllers\Grades;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\GradeStudentStatisticsRequest;
use App\Models\AcademicYear;
use App\Models\LibroDigital\School;
use App\Models\StudentProfile;
use App\Services\Grades\GradeStatisticsService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GradeStudentStatisticsController extends Controller
{
    public function __construct(
        private readonly LibroDigitalAccessContext $access,
        private readonly GradeStatisticsService $statistics,
    ) {}

    public function __invoke(GradeStudentStatisticsRequest $request): JsonResponse
    {
        [$school, $year, $filters] = $this->context($request);

        return response()->json($this->statistics->studentConsolidation(
            $school,
            $year,
            $filters,
            $request->string('search')->trim()->value() ?: null,
            $request->integer('page', 1),
            $request->integer('per_page', 25),
        ));
    }

    public function show(GradeStudentStatisticsRequest $request, StudentProfile $studentProfile): JsonResponse
    {
        [$school, $year, $filters] = $this->context($request);

        return response()->json($this->statistics->studentDetail($school, $year, $studentProfile->id, $filters));
    }

    /** @return array{School,AcademicYear,array<string,int|string|null>} */
    private function context(GradeStudentStatisticsRequest $request): array
    {
        $school = $this->access->resolveSchool($request);
        $years = $school->academicYears()
            ->select('academic_years.id', 'academic_years.name', 'academic_years.year', 'academic_years.is_active')
            ->orderByDesc('academic_years.year')
            ->get();
        $year = $request->integer('academic_year_id')
            ? $years->firstWhere('id', $request->integer('academic_year_id'))
            : ($years->firstWhere('year', (int) now()->year) ?? $years->firstWhere('is_active', true) ?? $years->first());

        if (! $year instanceof AcademicYear) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'El año académico no pertenece al establecimiento seleccionado.',
            ]);
        }

        $filters = [
            'course_section_id' => $request->integer('course_section_id') ?: null,
            'schedule_subject_id' => $request->integer('schedule_subject_id') ?: null,
            'assessment_period_code' => $request->string('assessment_period_code')->trim()->value() ?: null,
        ];

        return [$school, $year, $filters];
    }
}
