<?php

namespace App\Http\Controllers\Grades;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\GradeStatisticsRequest;
use App\Models\AcademicYear;
use App\Services\Grades\GradeStatisticsService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GradeStatisticsController extends Controller
{
    public function __construct(
        private readonly LibroDigitalAccessContext $access,
        private readonly GradeStatisticsService $statistics,
    ) {}

    public function __invoke(GradeStatisticsRequest $request): JsonResponse
    {
        $school = $this->access->resolveSchool($request);
        $years = $school->academicYears()
            ->select('academic_years.id', 'academic_years.name', 'academic_years.year', 'academic_years.starts_at', 'academic_years.ends_at', 'academic_years.is_active')
            ->orderByDesc('academic_years.year')
            ->get();

        if ($years->isEmpty()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'El establecimiento no tiene años académicos habilitados en Libro Digital.',
            ]);
        }

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

        return response()->json($this->statistics->dashboard($school, $year, $filters, $years));
    }
}
