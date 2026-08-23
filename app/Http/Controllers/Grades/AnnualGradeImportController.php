<?php

namespace App\Http\Controllers\Grades;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\ResolveAnnualGradeImportRowRequest;
use App\Http\Requests\Grades\StoreAnnualGradeImportRequest;
use App\Models\AcademicYear;
use App\Models\Grades\AnnualGradeImport;
use App\Models\Grades\AnnualGradeImportRow;
use App\Services\Grades\AnnualGradeImportService;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnualGradeImportController extends Controller
{
    public function __construct(
        private readonly LibroDigitalAccessContext $access,
        private readonly AnnualGradeImportService $imports,
        private readonly AuditEventWriter $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
        ]);
        $school = $this->access->resolveSchool($request);
        $year = $request->integer('academic_year_id')
            ? AcademicYear::query()->findOrFail($request->integer('academic_year_id'))
            : AcademicYear::query()->where('is_active', true)->orderByDesc('year')->firstOrFail();

        $imports = AnnualGradeImport::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->with('createdBy:id,name')
            ->latest('id')
            ->limit(20)
            ->get();
        $importIds = $imports->pluck('id');
        $unmatchedRows = AnnualGradeImportRow::query()
            ->whereIn('annual_grade_import_id', $importIds)
            ->whereNull('student_profile_id')
            ->with(['annualImport:id,version,original_filename', 'courseSection:id,display_name'])
            ->latest('annual_grade_import_id')
            ->orderBy('source_sheet')
            ->orderBy('source_row')
            ->limit(250)
            ->get();
        $usedByImport = AnnualGradeImportRow::query()
            ->whereIn('annual_grade_import_id', $importIds)
            ->whereNotNull('student_profile_id')
            ->get(['annual_grade_import_id', 'student_profile_id'])
            ->groupBy('annual_grade_import_id')
            ->map(fn (Collection $rows): Collection => $rows->pluck('student_profile_id')->mapWithKeys(fn ($id): array => [(int) $id => true]));
        $issues = DB::table('annual_grade_import_columns')
            ->whereIn('annual_grade_import_id', $importIds)
            ->where('mapping_status', '!=', 'mapped')
            ->selectRaw('annual_grade_import_id, mapping_status, source_course_name, source_subject_name, message, COUNT(*) as columns_count, SUM(cell_count) as cells_count')
            ->groupBy('annual_grade_import_id', 'mapping_status', 'source_course_name', 'source_subject_name', 'message')
            ->orderByDesc('annual_grade_import_id')
            ->orderBy('source_course_name')
            ->limit(250)
            ->get()
            ->map(fn ($issue): array => [
                'annual_grade_import_id' => (int) $issue->annual_grade_import_id,
                'status' => $issue->mapping_status,
                'course' => $issue->source_course_name,
                'subject' => $issue->source_subject_name,
                'message' => $issue->message,
                'columns' => (int) $issue->columns_count,
                'cells' => (int) $issue->cells_count,
            ]);
        $latest = $imports->first();

        return response()->json([
            'summary' => [
                'imports' => $imports->count(),
                'matched' => (int) ($latest?->matched_rows ?? 0),
                'unmatched' => (int) ($latest?->unmatched_rows ?? 0),
                'applied' => (int) ($latest?->applied_results ?? 0),
                'pending' => (int) ($latest?->pending_cells ?? 0),
                'blocked' => (int) ($latest?->blocked_cells ?? 0),
            ],
            'imports' => $imports->map(fn (AnnualGradeImport $import): array => $this->importPayload($import))->values(),
            'unmatched' => $unmatchedRows->map(function (AnnualGradeImportRow $row) use ($usedByImport): array {
                $used = $usedByImport->get($row->annual_grade_import_id, collect());

                return $this->rowPayload($row, $used);
            })->values(),
            'issues' => $issues->values(),
            'catalogs' => [
                'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active']),
                'school' => ['id' => $school->id, 'name' => $school->name],
            ],
        ]);
    }

    public function store(StoreAnnualGradeImportRequest $request): JsonResponse
    {
        $school = $this->access->resolveSchool($request);
        $year = AcademicYear::query()->findOrFail($request->integer('academic_year_id'));
        $result = $this->imports->import($request->file('file'), $year, $school, $request->user());
        $import = $result['import'];
        $this->audit->write(
            $result['duplicate'] ? 'grades.annual_import.retried' : 'grades.annual_import.created',
            $result['duplicate'] ? 'retry' : 'import',
            $import,
            actor: $request->user(),
            schoolId: $school->id,
            academicYearId: $year->id,
            after: $this->importPayload($import),
            request: $request,
            entityRevision: $import->version,
        );

        return response()->json([
            'message' => $result['duplicate']
                ? 'El archivo ya existía: no se duplicó información y se reintentaron los pendientes.'
                : 'El Excel anual fue procesado sin detenerse por filas pendientes de conciliación.',
            'duplicate' => $result['duplicate'],
            'data' => $this->importPayload($import),
        ], $result['duplicate'] ? 200 : 201);
    }

    public function retry(Request $request, AnnualGradeImport $annualGradeImport): JsonResponse
    {
        $school = $this->access->resolveSchool($request);
        $this->assertSchool($annualGradeImport, $school->id);
        $import = $this->imports->retry($annualGradeImport, $request->user());
        $this->audit->write('grades.annual_import.retried', 'retry', $import, actor: $request->user(), schoolId: $school->id, academicYearId: $import->academic_year_id, after: $this->importPayload($import), request: $request, entityRevision: $import->version);

        return response()->json(['message' => 'Se reintentaron de forma idempotente los matches y destinos pendientes.', 'data' => $this->importPayload($import)]);
    }

    public function candidates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'annual_grade_import_row_id' => ['required', 'integer', 'exists:annual_grade_import_rows,id'],
            'search' => ['required', 'string', 'min:2', 'max:120'],
        ]);
        $row = AnnualGradeImportRow::query()->with('annualImport')->findOrFail($data['annual_grade_import_row_id']);
        $school = $this->access->resolveSchool($request);
        $this->assertSchool($row->annualImport, $school->id);

        return response()->json(['data' => $this->imports->candidates($row, $data['search'])]);
    }

    public function resolve(ResolveAnnualGradeImportRowRequest $request, AnnualGradeImportRow $annualGradeImportRow): JsonResponse
    {
        $annualGradeImportRow->loadMissing('annualImport');
        $school = $this->access->resolveSchool($request);
        $this->assertSchool($annualGradeImportRow->annualImport, $school->id);
        $before = $annualGradeImportRow->only(['student_profile_id', 'student_enrollment_id', 'match_status']);
        $row = $this->imports->resolve(
            $annualGradeImportRow,
            $request->integer('student_profile_id'),
            $request->input('note'),
            $request->user(),
        );
        $this->audit->write('grades.annual_import.student_matched', 'manual_match', $row, actor: $request->user(), schoolId: $school->id, academicYearId: $row->annualImport->academic_year_id, before: $before, after: $row->only(['student_profile_id', 'student_enrollment_id', 'match_status']), reason: $request->input('note'), request: $request);

        return response()->json([
            'message' => 'La estudiante fue vinculada y sus calificaciones elegibles se reprocesaron.',
            'data' => $this->rowPayload($row),
        ]);
    }

    /** @return array<string,mixed> */
    private function importPayload(AnnualGradeImport $import): array
    {
        return [
            'id' => $import->id,
            'version' => (int) $import->version,
            'status' => $import->status,
            'filename' => $import->original_filename,
            'school_year' => (int) $import->school_year,
            'source_date_from' => $import->source_date_from?->format('Y-m-d'),
            'source_date_to' => $import->source_date_to?->format('Y-m-d'),
            'courses' => (int) $import->course_count,
            'student_rows' => (int) $import->student_rows,
            'columns' => (int) $import->column_count,
            'cells' => (int) $import->cell_count,
            'numeric_cells' => (int) $import->numeric_cells,
            'pending_cells' => (int) $import->pending_cells,
            'not_applicable_cells' => (int) $import->not_applicable_cells,
            'invalid_cells' => (int) $import->invalid_cells,
            'matched_rows' => (int) $import->matched_rows,
            'unmatched_rows' => (int) $import->unmatched_rows,
            'applied_results' => (int) $import->applied_results,
            'preserved_manual_results' => (int) $import->preserved_manual_results,
            'blocked_cells' => (int) $import->blocked_cells,
            'created_by' => $import->createdBy?->name,
            'created_at' => $import->created_at?->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function rowPayload(AnnualGradeImportRow $row, ?Collection $usedStudentIds = null): array
    {
        $candidates = collect($row->candidates ?? []);
        if ($usedStudentIds) {
            $candidates = $candidates->reject(fn (array $candidate): bool => $usedStudentIds->has((int) ($candidate['student_profile_id'] ?? 0)));
        }

        return [
            'id' => $row->id,
            'annual_grade_import_id' => $row->annual_grade_import_id,
            'import_version' => $row->annualImport?->version,
            'filename' => $row->annualImport?->original_filename,
            'source_sheet' => $row->source_sheet,
            'source_row' => (int) $row->source_row,
            'source_course_name' => $row->source_course_name,
            'course' => $row->courseSection?->display_name,
            'list_number' => $row->list_number,
            'source_name' => $row->source_name,
            'source_rut' => $row->source_rut,
            'match_status' => $row->match_status,
            'match_confidence' => $row->match_confidence,
            'candidates' => $candidates->values()->all(),
            'resolution_note' => $row->resolution_note,
            'matched_student' => $row->student ? [
                'id' => $row->student->id,
                'name' => $row->student->registered_name_resolved,
                'rut' => $row->student->rut,
            ] : null,
        ];
    }

    private function assertSchool(AnnualGradeImport $import, int $schoolId): void
    {
        if ((int) $import->school_id !== $schoolId) {
            abort(404);
        }
    }
}
