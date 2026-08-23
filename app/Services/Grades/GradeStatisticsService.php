<?php

namespace App\Services\Grades;

use App\Models\AcademicYear;
use App\Models\LibroDigital\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeStatisticsService
{
    private const COMPLETED_RESULT = "r.id IS NOT NULL AND r.status <> 'pending'";

    private const COMPARABLE_GRADE = "CASE
        WHEN r.numeric_value IS NOT NULL
            AND r.status = 'recorded'
            AND COALESCE(r.absent, 0) = 0
            AND COALESCE(r.exempt, 0) = 0
            AND gs.scale_type = 'numeric'
            AND gs.minimum_value IS NOT NULL
            AND gs.maximum_value IS NOT NULL
            AND gs.maximum_value > gs.minimum_value
            AND r.numeric_value BETWEEN gs.minimum_value AND gs.maximum_value
        THEN 1 + ((r.numeric_value - gs.minimum_value) * 6.0 / (gs.maximum_value - gs.minimum_value))
        ELSE NULL
    END";

    /**
     * @param  array{course_section_id:?int,schedule_subject_id:?int,assessment_period_code:?string}  $filters
     * @param  Collection<int,AcademicYear>  $years
     * @return array<string,mixed>
     */
    public function dashboard(School $school, AcademicYear $year, array $filters, Collection $years): array
    {
        $summary = $this->summary($school->id, $year->id, $filters);
        $byCourse = $this->grouped($school->id, $year->id, $filters, 'course');
        $bySubject = $this->grouped($school->id, $year->id, $filters, 'subject');

        return [
            'summary' => $summary,
            'distribution' => $this->distribution($school->id, $year->id, $filters),
            'evaluation_progress' => $this->evaluationProgress($school->id, $year->id, $filters),
            'by_course' => $byCourse,
            'by_subject' => $bySubject,
            'catalogs' => [
                'academic_years' => $years->map(fn (AcademicYear $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'year' => (int) $item->year,
                    'is_active' => (bool) $item->is_active,
                ])->values(),
                'courses' => $this->courseCatalog($school->id, $year->id),
                'subjects' => $this->subjectCatalog($school->id, $year->id),
                'periods' => $this->periodCatalog($school->id, $year->id),
            ],
            'meta' => [
                'school' => ['id' => $school->id, 'name' => $school->name],
                'academic_year' => ['id' => $year->id, 'name' => $year->name, 'year' => (int) $year->year],
                'filters' => $filters,
                'generated_at' => now()->toIso8601String(),
                'methodology' => [
                    'Las notas numéricas se normalizan a una escala equivalente de 1,0 a 7,0 usando la escala oficial de cada evaluación.',
                    'La progresión agrupa la primera, segunda y sucesivas evaluaciones de cada asignatura; no utiliza fechas estimadas.',
                    'En importaciones anuales, la cobertura considera la nómina vinculada al libro porque el archivo no informa la fecha real de cada evaluación.',
                    'Las celdas P se consideran pendientes; las celdas no aplicables no forman parte del universo esperado.',
                    'Los resultados ausentes o eximidos cuentan como gestionados, pero no alteran el promedio ni la aprobación.',
                ],
            ],
        ];
    }

    /** @return array<string,int|float|null> */
    private function summary(int $schoolId, int $yearId, array $filters): array
    {
        $assessmentCount = $this->assessmentScope($schoolId, $yearId, $filters)->distinct()->count('a.id');
        $row = $this->resultScope($schoolId, $yearId, $filters)
            ->selectRaw($this->coverageSelect())
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.self::COMPLETED_RESULT.' THEN r.student_profile_id END) AS students_evaluated')
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL THEN 1 ELSE 0 END) AS comparable_results')
            ->selectRaw('AVG('.self::COMPARABLE_GRADE.') AS average_grade')
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL AND r.numeric_value >= gs.passing_value THEN 1 ELSE 0 END) AS passed_results')
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL AND r.numeric_value < gs.passing_value THEN 1 ELSE 0 END) AS failed_results')
            ->first();

        $expected = (int) ($row->expected_results ?? 0);
        $completed = (int) ($row->completed_results ?? 0);
        $comparable = (int) ($row->comparable_results ?? 0);
        $passed = (int) ($row->passed_results ?? 0);

        return [
            'assessments' => $assessmentCount,
            'students_evaluated' => (int) ($row->students_evaluated ?? 0),
            'expected_results' => $expected,
            'completed_results' => $completed,
            'pending_results' => max(0, $expected - $completed),
            'comparable_results' => $comparable,
            'average_grade' => $this->roundNullable($row->average_grade ?? null),
            'approval_rate' => $this->percent($passed, $comparable),
            'coverage_rate' => $this->percent($completed, $expected),
            'passed_results' => $passed,
            'failed_results' => (int) ($row->failed_results ?? 0),
            'absent_results' => (int) ($row->absent_results ?? 0),
            'exempt_results' => (int) ($row->exempt_results ?? 0),
            'imported_results' => (int) ($row->imported_results ?? 0),
            'manual_results' => (int) ($row->manual_results ?? 0),
        ];
    }

    /** @return array<int,array<string,int|float|string|null>> */
    private function distribution(int $schoolId, int $yearId, array $filters): array
    {
        $grade = self::COMPARABLE_GRADE;
        $row = $this->resultScope($schoolId, $yearId, $filters)
            ->selectRaw("SUM(CASE WHEN {$grade} >= 1 AND {$grade} < 4 THEN 1 ELSE 0 END) AS insufficient")
            ->selectRaw("SUM(CASE WHEN {$grade} >= 4 AND {$grade} < 5 THEN 1 ELSE 0 END) AS sufficient")
            ->selectRaw("SUM(CASE WHEN {$grade} >= 5 AND {$grade} < 6 THEN 1 ELSE 0 END) AS good")
            ->selectRaw("SUM(CASE WHEN {$grade} >= 6 AND {$grade} <= 7 THEN 1 ELSE 0 END) AS outstanding")
            ->first();

        return [
            ['key' => 'insufficient', 'label' => '1,0 – 3,9', 'description' => 'Insuficiente', 'count' => (int) ($row->insufficient ?? 0)],
            ['key' => 'sufficient', 'label' => '4,0 – 4,9', 'description' => 'Suficiente', 'count' => (int) ($row->sufficient ?? 0)],
            ['key' => 'good', 'label' => '5,0 – 5,9', 'description' => 'Bueno', 'count' => (int) ($row->good ?? 0)],
            ['key' => 'outstanding', 'label' => '6,0 – 7,0', 'description' => 'Destacado', 'count' => (int) ($row->outstanding ?? 0)],
        ];
    }

    /** @return array<int,array<string,int|float|null>> */
    private function evaluationProgress(int $schoolId, int $yearId, array $filters): array
    {
        $sequencedAssessments = $this->assessmentScope($schoolId, $yearId, $filters)
            ->select([
                'a.id',
                'a.book_id',
                'a.schedule_subject_id',
                'a.grading_scheme_id',
            ])
            ->selectRaw('ROW_NUMBER() OVER (
                PARTITION BY a.book_id, a.schedule_subject_id
                ORDER BY a.id
            ) AS evaluation_number');

        $grade = self::COMPARABLE_GRADE;
        $rows = DB::query()
            ->fromSub($sequencedAssessments, 'sequence')
            ->join('lcd_grading_schemes as gs', 'gs.id', '=', 'sequence.grading_scheme_id')
            ->leftJoin('lcd_student_results as r', 'r.assessment_id', '=', 'sequence.id')
            ->select('sequence.evaluation_number')
            ->selectRaw('COUNT(DISTINCT sequence.id) AS assessments')
            ->selectRaw("SUM(CASE WHEN {$grade} IS NOT NULL THEN 1 ELSE 0 END) AS results")
            ->selectRaw("SUM(CASE WHEN {$grade} IS NOT NULL THEN {$grade} ELSE 0 END) AS grade_sum")
            ->selectRaw("SUM(CASE WHEN {$grade} IS NOT NULL AND r.numeric_value >= gs.passing_value THEN 1 ELSE 0 END) AS passed")
            ->groupBy('sequence.evaluation_number')
            ->orderBy('sequence.evaluation_number')
            ->get();

        return $rows->map(function ($row): array {
            $results = (int) $row->results;

            return [
                'evaluation_number' => (int) $row->evaluation_number,
                'label' => 'Evaluación '.(int) $row->evaluation_number,
                'assessments' => (int) $row->assessments,
                'average_grade' => $results > 0 ? round((float) $row->grade_sum / $results, 2) : null,
                'approval_rate' => $this->percent((int) $row->passed, $results),
                'results' => $results,
            ];
        })->values()->all();
    }

    /**
     * Consolida resultados nominales sin mezclarlos con el endpoint institucional agregado.
     *
     * @param  array{course_section_id:?int,schedule_subject_id:?int,assessment_period_code:?string}  $filters
     * @return array{data:array<int,array<string,mixed>>,pagination:array<string,int>}
     */
    public function studentConsolidation(
        School $school,
        AcademicYear $year,
        array $filters,
        ?string $search = null,
        int $page = 1,
        int $perPage = 25,
    ): array {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));

        $studentScope = $this->studentScope($school->id, $year->id, $filters, $search);
        $total = (clone $studentScope)->distinct()->count('sp.id');
        $students = (clone $studentScope)
            ->select('sp.id', 'sp.registered_name', 'sp.first_name', 'sp.last_name')
            ->distinct()
            ->orderByRaw("COALESCE(NULLIF(sp.registered_name, ''), NULLIF(sp.last_name, ''), NULLIF(sp.first_name, ''), 'Sin nombre')")
            ->orderBy('sp.id')
            ->forPage($page, $perPage)
            ->get();

        if ($students->isEmpty()) {
            return [
                'data' => [],
                'pagination' => $this->pagination($page, $perPage, $total),
            ];
        }

        $studentIds = $students->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $rows = $this->resultScope($school->id, $year->id, $filters)
            ->join('student_profiles as sp', 'sp.id', '=', 'el.student_profile_id')
            ->leftJoin('course_sections as course_dimension', 'course_dimension.id', '=', 'b.course_section_id')
            ->whereIn('sp.id', $studentIds)
            ->selectRaw("sp.id AS student_id, b.course_section_id, COALESCE(course_dimension.display_name, b.course_label, 'Sin curso') AS course_name")
            ->selectRaw('COUNT(DISTINCT a.id) AS assessments')
            ->selectRaw($this->coverageSelect())
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL THEN 1 ELSE 0 END) AS comparable_results')
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL THEN '.self::COMPARABLE_GRADE.' ELSE 0 END) AS grade_sum')
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL AND r.numeric_value >= gs.passing_value THEN 1 ELSE 0 END) AS passed_results')
            ->groupBy('sp.id', 'b.course_section_id', 'course_dimension.display_name', 'b.course_label')
            ->get()
            ->groupBy('student_id');

        $data = $students->map(function ($student) use ($rows): array {
            $statistics = $rows->get($student->id, collect());
            $expected = (int) $statistics->sum('expected_results');
            $completed = (int) $statistics->sum('completed_results');
            $comparable = (int) $statistics->sum('comparable_results');
            $passed = (int) $statistics->sum('passed_results');
            $name = trim((string) ($student->registered_name ?: trim($student->first_name.' '.$student->last_name)));

            return [
                'student_profile_id' => (int) $student->id,
                'name' => $name !== '' ? $name : 'Alumna sin nombre registrado',
                'courses' => $statistics->map(fn ($row): array => [
                    'id' => $row->course_section_id ? (int) $row->course_section_id : null,
                    'name' => $row->course_name,
                ])->unique('id')->values()->all(),
                'assessments' => (int) $statistics->sum('assessments'),
                'expected_results' => $expected,
                'completed_results' => $completed,
                'pending_results' => max(0, $expected - $completed),
                'comparable_results' => $comparable,
                'average_grade' => $comparable > 0
                    ? round((float) $statistics->sum('grade_sum') / $comparable, 2)
                    : null,
                'approval_rate' => $this->percent($passed, $comparable),
                'coverage_rate' => $this->percent($completed, $expected),
                'absent_results' => (int) $statistics->sum('absent_results'),
                'exempt_results' => (int) $statistics->sum('exempt_results'),
            ];
        })->values()->all();

        return [
            'data' => $data,
            'pagination' => $this->pagination($page, $perPage, $total),
        ];
    }

    /**
     * Entrega el detalle nominal de una alumna dentro del contexto académico autorizado.
     *
     * @param  array{course_section_id:?int,schedule_subject_id:?int,assessment_period_code:?string}  $filters
     * @return array<string,mixed>
     */
    public function studentDetail(School $school, AcademicYear $year, int $studentProfileId, array $filters): array
    {
        $student = DB::table('student_profiles')
            ->where('id', $studentProfileId)
            ->first(['id', 'registered_name', 'first_name', 'last_name']);

        $rows = $this->resultScope($school->id, $year->id, $filters)
            ->join('schedule_subjects as detail_subject', 'detail_subject.id', '=', 'a.schedule_subject_id')
            ->leftJoin('course_sections as detail_course', 'detail_course.id', '=', 'b.course_section_id')
            ->where('el.student_profile_id', $studentProfileId)
            ->select([
                'a.id as assessment_id',
                'a.book_id',
                'a.schedule_subject_id',
                'a.name as assessment_name',
                'a.code as assessment_code',
                'detail_subject.name as subject_name',
                'b.course_section_id',
                'r.id as result_id',
                'r.status as result_status',
                'r.numeric_value',
                'r.qualitative_value',
                'r.absent',
                'r.exempt',
                'r.annual_grade_import_id',
            ])
            ->selectRaw("COALESCE(detail_course.display_name, b.course_label, 'Sin curso') AS course_name")
            ->selectRaw('CASE WHEN na.student_profile_id IS NULL THEN 1 ELSE 0 END AS is_applicable')
            ->selectRaw(self::COMPARABLE_GRADE.' AS normalized_grade')
            ->selectRaw('CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL AND r.numeric_value >= gs.passing_value THEN 1 ELSE 0 END AS passed')
            ->orderBy('b.course_section_id')
            ->orderBy('a.schedule_subject_id')
            ->orderBy('a.id')
            ->get();

        if (! $student || $rows->isEmpty()) {
            throw ValidationException::withMessages([
                'student_profile_id' => 'La alumna no tiene evaluaciones dentro del contexto académico autorizado.',
            ]);
        }

        $sequence = [];
        $evaluations = $rows->map(function ($row) use (&$sequence): array {
            $key = $row->book_id.'|'.$row->schedule_subject_id;
            $sequence[$key] = ($sequence[$key] ?? 0) + 1;
            $applicable = (bool) $row->is_applicable;
            $state = $this->studentResultState($row, $applicable);

            return [
                'assessment_id' => (int) $row->assessment_id,
                'evaluation_number' => $sequence[$key],
                'label' => 'Evaluación '.$sequence[$key],
                'name' => $row->assessment_name,
                'code' => $row->assessment_code,
                'subject' => ['id' => (int) $row->schedule_subject_id, 'name' => $row->subject_name],
                'course' => ['id' => $row->course_section_id ? (int) $row->course_section_id : null, 'name' => $row->course_name],
                'state' => $state,
                'state_label' => $this->studentResultStateLabel($state),
                'grade' => $row->normalized_grade !== null ? round((float) $row->normalized_grade, 2) : null,
                'qualitative_value' => $row->qualitative_value,
                'passed' => $row->normalized_grade !== null ? (bool) $row->passed : null,
                'source' => ! $row->result_id
                    ? null
                    : ($row->annual_grade_import_id ? 'annual_import' : 'direct_entry'),
                'applicable' => $applicable,
            ];
        });

        $applicable = $evaluations->where('applicable', true);
        $completed = $applicable->whereIn('state', ['recorded', 'absent', 'exempt']);
        $comparable = $applicable->whereNotNull('grade');
        $passed = $comparable->where('passed', true)->count();
        $name = trim((string) ($student->registered_name ?: trim($student->first_name.' '.$student->last_name)));

        $bySubject = $evaluations->groupBy('subject.id')->map(function (Collection $subjectRows): array {
            $subjectApplicable = $subjectRows->where('applicable', true);
            $subjectCompleted = $subjectApplicable->whereIn('state', ['recorded', 'absent', 'exempt']);
            $subjectComparable = $subjectApplicable->whereNotNull('grade');

            return [
                'id' => $subjectRows->first()['subject']['id'],
                'name' => $subjectRows->first()['subject']['name'],
                'assessments' => $subjectRows->count(),
                'expected_results' => $subjectApplicable->count(),
                'completed_results' => $subjectCompleted->count(),
                'pending_results' => max(0, $subjectApplicable->count() - $subjectCompleted->count()),
                'average_grade' => $subjectComparable->isNotEmpty() ? round((float) $subjectComparable->avg('grade'), 2) : null,
                'approval_rate' => $this->percent($subjectComparable->where('passed', true)->count(), $subjectComparable->count()),
                'coverage_rate' => $this->percent($subjectCompleted->count(), $subjectApplicable->count()),
            ];
        })->sortBy('name')->values()->all();

        return [
            'student' => [
                'student_profile_id' => (int) $student->id,
                'name' => $name !== '' ? $name : 'Alumna sin nombre registrado',
                'courses' => $evaluations->pluck('course')->unique('id')->values()->all(),
            ],
            'summary' => [
                'assessments' => $evaluations->count(),
                'expected_results' => $applicable->count(),
                'completed_results' => $completed->count(),
                'pending_results' => max(0, $applicable->count() - $completed->count()),
                'comparable_results' => $comparable->count(),
                'average_grade' => $comparable->isNotEmpty() ? round((float) $comparable->avg('grade'), 2) : null,
                'approval_rate' => $this->percent($passed, $comparable->count()),
                'coverage_rate' => $this->percent($completed->count(), $applicable->count()),
            ],
            'by_subject' => $bySubject,
            'evaluations' => $evaluations->values()->all(),
        ];
    }

    /** @return array<int,array<string,int|float|string|null>> */
    private function grouped(int $schoolId, int $yearId, array $filters, string $dimension): array
    {
        $query = $this->resultScope($schoolId, $yearId, $filters);
        if ($dimension === 'course') {
            $query->leftJoin('course_sections as dimension', 'dimension.id', '=', 'b.course_section_id');
            $id = 'b.course_section_id';
            $name = "COALESCE(dimension.display_name, b.course_label, 'Sin curso')";
        } else {
            $query->join('schedule_subjects as dimension', 'dimension.id', '=', 'a.schedule_subject_id');
            $id = 'a.schedule_subject_id';
            $name = 'dimension.name';
        }

        $rows = $query
            ->selectRaw("{$id} AS id, {$name} AS name")
            ->selectRaw('COUNT(DISTINCT a.id) AS assessments')
            ->selectRaw($this->coverageSelect())
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL THEN 1 ELSE 0 END) AS comparable_results')
            ->selectRaw('AVG('.self::COMPARABLE_GRADE.') AS average_grade')
            ->selectRaw('SUM(CASE WHEN '.self::COMPARABLE_GRADE.' IS NOT NULL AND r.numeric_value >= gs.passing_value THEN 1 ELSE 0 END) AS passed_results')
            ->groupBy($id)
            ->groupByRaw($name)
            ->get();

        return $rows->map(function ($row): array {
            $expected = (int) $row->expected_results;
            $completed = (int) $row->completed_results;
            $comparable = (int) $row->comparable_results;

            return [
                'id' => $row->id ? (int) $row->id : null,
                'name' => $row->name,
                'assessments' => (int) $row->assessments,
                'expected_results' => $expected,
                'completed_results' => $completed,
                'pending_results' => max(0, $expected - $completed),
                'comparable_results' => $comparable,
                'average_grade' => $this->roundNullable($row->average_grade),
                'approval_rate' => $this->percent((int) $row->passed_results, $comparable),
                'coverage_rate' => $this->percent($completed, $expected),
            ];
        })->sortByDesc(fn (array $row): array => [$row['expected_results'], $row['name']])->values()->all();
    }

    private function assessmentScope(int $schoolId, int $yearId, array $filters): Builder
    {
        $query = DB::table('lcd_assessments as a')
            ->join('lcd_books as b', 'b.id', '=', 'a.book_id')
            ->where('a.school_id', $schoolId)
            ->where('b.school_id', $schoolId)
            ->where('b.academic_year_id', $yearId)
            ->where('a.status', '<>', 'cancelled');

        return $this->applyFilters($query, $filters);
    }

    private function resultScope(int $schoolId, int $yearId, array $filters): Builder
    {
        return $this->assessmentScope($schoolId, $yearId, $filters)
            ->join('lcd_grading_schemes as gs', 'gs.id', '=', 'a.grading_scheme_id')
            ->leftJoin('lcd_enrollment_links as el', function ($join): void {
                $join->on('el.teaching_group_id', '=', 'a.teaching_group_id')
                    ->where(function ($roster): void {
                        $roster->whereNotNull('a.annual_import_key')
                            ->orWhere(function ($datedRoster): void {
                                $datedRoster->whereColumn('el.effective_from', '<=', 'a.assessment_date')
                                    ->whereRaw('(el.effective_to IS NULL OR el.effective_to >= a.assessment_date)');
                            });
                    });
            })
            ->leftJoinSub($this->notApplicableResults(), 'na', function ($join): void {
                $join->on('na.assessment_id', '=', 'a.id')
                    ->on('na.student_profile_id', '=', 'el.student_profile_id');
            })
            ->leftJoin('lcd_student_results as r', function ($join): void {
                $join->on('r.assessment_id', '=', 'a.id')
                    ->on('r.student_profile_id', '=', 'el.student_profile_id');
            });
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['course_section_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('b.course_section_id', $id))
            ->when($filters['schedule_subject_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('a.schedule_subject_id', $id))
            ->when($filters['assessment_period_code'] ?? null, function (Builder $builder, string $code): void {
                $builder->whereIn('a.assessment_period_id', DB::table('lcd_assessment_periods')->select('id')->where('code', $code));
            });
    }

    private function studentScope(int $schoolId, int $yearId, array $filters, ?string $search): Builder
    {
        $query = $this->resultScope($schoolId, $yearId, $filters)
            ->join('student_profiles as sp', 'sp.id', '=', 'el.student_profile_id');

        if (filled($search)) {
            $needle = '%'.trim((string) $search).'%';
            $query->where(function (Builder $students) use ($needle): void {
                $students->where('sp.registered_name', 'like', $needle)
                    ->orWhere('sp.first_name', 'like', $needle)
                    ->orWhere('sp.last_name', 'like', $needle)
                    ->orWhere('sp.rut', 'like', $needle);
            });
        }

        return $query;
    }

    /** @return array{page:int,per_page:int,total:int,last_page:int} */
    private function pagination(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function studentResultState(object $row, bool $applicable): string
    {
        if (! $applicable) {
            return 'not_applicable';
        }
        if (! $row->result_id) {
            return 'missing';
        }
        if ($row->result_status === 'pending') {
            return 'pending';
        }
        if ($row->absent) {
            return 'absent';
        }
        if ($row->exempt) {
            return 'exempt';
        }

        return 'recorded';
    }

    private function studentResultStateLabel(string $state): string
    {
        return match ($state) {
            'recorded' => 'Registrada',
            'pending' => 'Pendiente (P)',
            'absent' => 'Ausente',
            'exempt' => 'Eximida',
            'not_applicable' => 'No aplica',
            default => 'Sin registrar',
        };
    }

    private function coverageSelect(): string
    {
        $eligible = $this->eligibleRosterExpression();

        return "SUM(CASE WHEN {$eligible} THEN 1 ELSE 0 END) AS expected_results,
            SUM(CASE WHEN {$eligible} AND ".self::COMPLETED_RESULT." THEN 1 ELSE 0 END) AS completed_results,
            SUM(CASE WHEN {$eligible} AND ".self::COMPLETED_RESULT." AND COALESCE(r.absent, 0) = 1 THEN 1 ELSE 0 END) AS absent_results,
            SUM(CASE WHEN {$eligible} AND ".self::COMPLETED_RESULT." AND COALESCE(r.exempt, 0) = 1 THEN 1 ELSE 0 END) AS exempt_results,
            SUM(CASE WHEN {$eligible} AND ".self::COMPLETED_RESULT." AND r.annual_grade_import_id IS NOT NULL THEN 1 ELSE 0 END) AS imported_results,
            SUM(CASE WHEN {$eligible} AND ".self::COMPLETED_RESULT.' AND r.annual_grade_import_id IS NULL THEN 1 ELSE 0 END) AS manual_results';
    }

    private function eligibleRosterExpression(): string
    {
        return 'el.id IS NOT NULL AND na.student_profile_id IS NULL';
    }

    private function notApplicableResults(): Builder
    {
        return DB::table('annual_grade_import_columns as agic')
            ->join('annual_grade_import_rows as agir', function ($join): void {
                $join->on('agir.annual_grade_import_id', '=', 'agic.annual_grade_import_id')
                    ->whereNotNull('agir.student_profile_id');
            })
            ->join('annual_grade_import_cells as agix', function ($join): void {
                $join->on('agix.annual_grade_import_column_id', '=', 'agic.id')
                    ->on('agix.annual_grade_import_row_id', '=', 'agir.id')
                    ->where('agix.value_kind', 'not_applicable');
            })
            ->whereNotNull('agic.assessment_id')
            ->select('agic.assessment_id', 'agir.student_profile_id')
            ->distinct();
    }

    /** @return array<int,array{id:int,name:string}> */
    private function courseCatalog(int $schoolId, int $yearId): array
    {
        return DB::table('lcd_books as b')
            ->join('course_sections as c', 'c.id', '=', 'b.course_section_id')
            ->where('b.school_id', $schoolId)
            ->where('b.academic_year_id', $yearId)
            ->select('c.id', 'c.display_name as name')
            ->distinct()->orderBy('c.display_name')->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => $row->name])->all();
    }

    /** @return array<int,array{id:int,name:string}> */
    private function subjectCatalog(int $schoolId, int $yearId): array
    {
        return DB::table('lcd_assessments as a')
            ->join('lcd_books as b', 'b.id', '=', 'a.book_id')
            ->join('schedule_subjects as s', 's.id', '=', 'a.schedule_subject_id')
            ->where('a.school_id', $schoolId)->where('b.academic_year_id', $yearId)
            ->where('a.status', '<>', 'cancelled')
            ->select('s.id', 's.name')->distinct()->orderBy('s.name')->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => $row->name])->all();
    }

    /** @return array<int,array{id:string,name:string}> */
    private function periodCatalog(int $schoolId, int $yearId): array
    {
        return DB::table('lcd_assessment_periods as p')
            ->join('lcd_books as b', 'b.id', '=', 'p.book_id')
            ->where('p.school_id', $schoolId)->where('b.academic_year_id', $yearId)
            ->select('p.code as id', 'p.name')->distinct()->orderBy('p.name')->get()
            ->map(fn ($row): array => ['id' => $row->id, 'name' => $row->name])->all();
    }

    private function percent(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : null;
    }

    private function roundNullable(mixed $value): ?float
    {
        return $value !== null ? round((float) $value, 2) : null;
    }
}
