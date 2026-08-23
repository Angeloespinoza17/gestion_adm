<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ResolveMonthlyAttendanceRowRequest;
use App\Http\Requests\Attendance\StoreMonthlyAttendanceImportRequest;
use App\Models\AcademicYear;
use App\Models\Attendance\MonthlyAttendanceImport;
use App\Models\Attendance\MonthlyAttendanceImportRow;
use App\Models\StudentEnrollment;
use App\Services\Attendance\MonthlyAttendanceImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MonthlyAttendanceImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $yearId = isset($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : AcademicYear::query()->where('is_active', true)->value('id');

        $imports = MonthlyAttendanceImport::query()
            ->with('createdBy:id,name')
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->when(isset($filters['month']), fn ($query) => $query->where('month', $filters['month']))
            ->orderByDesc('school_year')
            ->orderByDesc('month')
            ->orderByDesc('version')
            ->limit(24)
            ->get()
            ->map(fn (MonthlyAttendanceImport $import): array => $this->importPayload($import));

        $unmatchedQuery = MonthlyAttendanceImportRow::query()
            ->whereNull('student_profile_id')
            ->whereHas('monthlyImport', fn ($query) => $query
                ->where('is_active', true)
                ->when($yearId, fn ($inner) => $inner->where('academic_year_id', $yearId))
                ->when(isset($filters['month']), fn ($inner) => $inner->where('month', $filters['month'])));
        $unmatchedCount = (clone $unmatchedQuery)->count();
        $unmatchedRows = $unmatchedQuery
            ->with([
                'monthlyImport:id,academic_year_id,school_year,month,version,original_filename,is_active',
                'courseSection:id,display_name',
            ])
            ->orderBy('source_course_name')
            ->orderBy('source_name')
            ->limit(2000)
            ->get();
        $usedStudentsByImport = MonthlyAttendanceImportRow::query()
            ->whereIn('monthly_attendance_import_id', $unmatchedRows->pluck('monthly_attendance_import_id')->unique())
            ->whereNotNull('student_profile_id')
            ->get(['monthly_attendance_import_id', 'student_profile_id'])
            ->groupBy('monthly_attendance_import_id')
            ->map(fn (Collection $rows): Collection => $rows
                ->pluck('student_profile_id')
                ->mapWithKeys(fn ($studentProfileId): array => [(int) $studentProfileId => true]));
        $unmatched = $unmatchedRows->map(fn (MonthlyAttendanceImportRow $row): array => $this->rowPayload(
            $row,
            $usedStudentsByImport->get($row->monthly_attendance_import_id, collect()),
        ));

        return response()->json([
            'catalogs' => [
                'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active', 'is_closed']),
            ],
            'summary' => [
                'imports' => $imports->count(),
                'active_imports' => $imports->where('is_active', true)->count(),
                'unmatched' => $unmatchedCount,
                'matched' => $imports->where('is_active', true)->sum('matched_rows'),
            ],
            'imports' => $imports,
            'unmatched' => $unmatched,
        ]);
    }

    public function store(
        StoreMonthlyAttendanceImportRequest $request,
        MonthlyAttendanceImportService $imports,
    ): JsonResponse {
        $year = AcademicYear::query()->findOrFail($request->integer('academic_year_id'));
        $import = $imports->import($request->file('file'), $year, $request->user());

        return response()->json([
            'message' => $import->wasRecentlyCreated
                ? 'Excel mensual procesado correctamente.'
                : 'Este mismo Excel ya estaba cargado; no se duplicaron registros.',
            'data' => $this->importPayload($import),
        ], $import->wasRecentlyCreated ? 201 : 200);
    }

    public function candidates(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'monthly_attendance_import_row_id' => ['required', 'integer', 'exists:monthly_attendance_import_rows,id'],
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $search = trim($filters['search']);
        $row = MonthlyAttendanceImportRow::query()
            ->with('monthlyImport:id,academic_year_id,is_active')
            ->findOrFail($filters['monthly_attendance_import_row_id']);

        if (! $row->monthlyImport?->is_active || $row->student_profile_id) {
            throw ValidationException::withMessages([
                'row' => 'Este caso ya no está disponible para conciliación.',
            ]);
        }

        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $row->monthlyImport->academic_year_id)
            ->whereNotIn('student_profile_id', function ($query) use ($row): void {
                $query->select('student_profile_id')
                    ->from('monthly_attendance_import_rows')
                    ->where('monthly_attendance_import_id', $row->monthly_attendance_import_id)
                    ->whereNotNull('student_profile_id');
            })
            ->whereHas('studentProfile', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('registered_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%");
            }))
            ->with(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->unique('student_profile_id')
            ->take(20)
            ->map(fn (StudentEnrollment $enrollment): array => [
                'student_profile_id' => $enrollment->student_profile_id,
                'student_enrollment_id' => $enrollment->id,
                'name' => $enrollment->studentProfile?->registered_name ?: trim($enrollment->studentProfile?->first_name.' '.$enrollment->studentProfile?->last_name),
                'rut' => $enrollment->studentProfile?->rut,
                'course' => $enrollment->courseSection?->display_name,
                'score' => null,
            ])
            ->values();

        return response()->json(['data' => $enrollments]);
    }

    public function resolve(
        ResolveMonthlyAttendanceRowRequest $request,
        MonthlyAttendanceImportRow $monthlyAttendanceImportRow,
        MonthlyAttendanceImportService $imports,
    ): JsonResponse {
        $row = $imports->resolve(
            $monthlyAttendanceImportRow,
            $request->integer('student_profile_id'),
            $request->validated('note'),
            $request->user(),
        );

        return response()->json([
            'message' => 'Caso conciliado. La asistencia y los indicadores quedaron asociados a la alumna.',
            'data' => $this->rowPayload($row),
        ]);
    }

    /** @return array<string,mixed> */
    private function importPayload(MonthlyAttendanceImport $import): array
    {
        return [
            'id' => $import->id,
            'academic_year_id' => $import->academic_year_id,
            'year' => $import->school_year,
            'month' => $import->month,
            'period' => sprintf('%04d-%02d', $import->school_year, $import->month),
            'version' => $import->version,
            'is_active' => $import->is_active,
            'status' => $import->status,
            'filename' => $import->original_filename,
            'sheet_count' => $import->sheet_count,
            'parsed_rows' => $import->parsed_rows,
            'matched_rows' => $import->matched_rows,
            'unmatched_rows' => $import->unmatched_rows,
            'imported_records' => $import->imported_records,
            'preserved_manual_records' => $import->preserved_manual_records,
            'metadata' => $import->metadata,
            'created_by' => $import->createdBy?->name,
            'completed_at' => $import->completed_at?->toIso8601String(),
            'superseded_at' => $import->superseded_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function rowPayload(MonthlyAttendanceImportRow $row, ?Collection $usedStudentIds = null): array
    {
        $candidates = collect($row->candidates ?: []);
        if ($usedStudentIds !== null) {
            $candidates = $candidates->reject(
                fn (array $candidate): bool => $usedStudentIds->has((int) $candidate['student_profile_id']),
            );
        }

        return [
            'id' => $row->id,
            'import_id' => $row->monthly_attendance_import_id,
            'academic_year_id' => $row->monthlyImport?->academic_year_id,
            'period' => $row->monthlyImport ? sprintf('%04d-%02d', $row->monthlyImport->school_year, $row->monthlyImport->month) : null,
            'source_sheet' => $row->source_sheet,
            'source_row' => $row->source_row,
            'source_course_name' => $row->source_course_name,
            'source_name' => $row->source_name,
            'source_rut' => $row->source_rut,
            'present_days' => $row->present_days,
            'absent_days' => $row->absent_days,
            'class_days' => $row->class_days,
            'attendance_rate' => $row->attendance_rate,
            'is_sep_priority' => $row->is_sep_priority,
            'is_sep_preferential' => $row->is_sep_preferential,
            'is_pie' => $row->is_pie,
            'match_status' => $row->match_status,
            'match_confidence' => $row->match_confidence,
            'candidates' => $candidates->values()->all(),
            'filename' => $row->monthlyImport?->original_filename,
            'matched_student' => $row->student ? [
                'id' => $row->student->id,
                'name' => $row->student->registered_name ?: trim($row->student->first_name.' '.$row->student->last_name),
                'rut' => $row->student->rut,
            ] : null,
            'matched_course' => $row->courseSection?->display_name,
        ];
    }
}
