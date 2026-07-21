<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceAlertIndexRequest;
use App\Http\Requests\Attendance\AttendanceDashboardRequest;
use App\Http\Requests\Attendance\AttendanceStudentIndexRequest;
use App\Http\Requests\Attendance\ConfirmAttendanceImportRequest;
use App\Http\Requests\Attendance\PreviewAttendanceImportRequest;
use App\Http\Requests\Attendance\StoreAttendanceFollowupRequest;
use App\Http\Requests\Attendance\UpdateAttendanceProjectionRequest;
use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceImport;
use App\Models\Attendance\AttendanceProjectionSetting;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\SchoolDay;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Services\Attendance\AttendanceAlertService;
use App\Services\Attendance\AttendanceImportService;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Attendance\AttendanceStatisticsAuditService;
use App\Services\Attendance\AttendanceStatisticsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceStatisticsCache $statisticsCache,
        private readonly AttendanceStatisticsAuditService $statisticsAudit,
    ) {}

    public function dashboard(AttendanceDashboardRequest $request, AttendanceReportService $reports): JsonResponse
    {
        return response()->json($reports->dashboard($request->validated(), $request->user()));
    }

    public function alerts(AttendanceAlertIndexRequest $request, AttendanceReportService $reports): JsonResponse
    {
        return response()->json($reports->alertDetails($request->validated()));
    }

    public function students(AttendanceStudentIndexRequest $request, AttendanceReportService $reports): JsonResponse
    {
        return response()->json($reports->studentDetails($request->validated()));
    }

    public function preview(
        PreviewAttendanceImportRequest $request,
        AttendanceImportService $imports,
    ): JsonResponse {
        $course = CourseSection::query()->with('academicYear')->findOrFail($request->integer('course_section_id'));
        $import = $imports->preview($request->file('file'), $course, $request->user());

        return response()->json($this->importPayload($import), $import->wasRecentlyCreated ? 201 : 200);
    }

    public function confirm(
        ConfirmAttendanceImportRequest $request,
        AttendanceImport $attendanceImport,
        AttendanceImportService $imports,
    ): JsonResponse {
        $import = $imports->confirm($attendanceImport, $request->validated(), $request->user());

        return response()->json($this->importPayload($import));
    }

    public function student(
        AttendanceDashboardRequest $request,
        StudentProfile $studentProfile,
        AttendanceReportService $reports,
    ): JsonResponse {
        $yearId = $request->integer('academic_year_id') ?: AcademicYear::query()->where('is_active', true)->value('id');
        abort_unless($yearId, 404, 'No existe un año académico disponible.');

        return response()->json($reports->student($yearId, $studentProfile, $request->integer('course_section_id') ?: null));
    }

    public function day(
        Request $request,
        SchoolDay $schoolDay,
        AttendanceReportService $reports,
    ): JsonResponse {
        $data = $request->validate([
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
        ]);

        return response()->json($reports->day($schoolDay, isset($data['course_section_id']) ? (int) $data['course_section_id'] : null));
    }

    public function updateAlert(Request $request, AttendanceAlert $attendanceAlert): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'acknowledged', 'in_progress', 'resolved'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $payload = ['status' => $data['status'], 'assigned_to' => $data['assigned_to'] ?? $attendanceAlert->assigned_to];
        if ($data['status'] === 'acknowledged') {
            $payload['acknowledged_at'] = now();
            $payload['acknowledged_by'] = $request->user()?->id;
        }
        if ($data['status'] === 'resolved') {
            $payload['resolved_at'] = now();
            $payload['resolved_by'] = $request->user()?->id;
        }
        $attendanceAlert->update($payload);
        $this->statisticsCache->invalidate();

        return response()->json($attendanceAlert->fresh());
    }

    public function updateSchoolDay(Request $request, SchoolDay $schoolDay): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'pending_confirmation'])],
            'label' => ['nullable', 'string', 'max:255'],
        ]);
        $before = $schoolDay->getAttributes();
        $schoolDay->update([
            ...$data,
            'updated_by' => $request->user()?->id,
        ]);
        $this->statisticsAudit->log('school_day_updated', $schoolDay, $request->user(), $before, $schoolDay->fresh()->getAttributes(), request: $request);
        $this->statisticsCache->invalidate();

        return response()->json($schoolDay->fresh());
    }

    public function updateRecord(
        Request $request,
        AttendanceRecord $attendanceRecord,
        AttendanceAlertService $alerts,
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in([AttendanceRecord::PRESENT, AttendanceRecord::ABSENT])],
            'absence_reason_id' => ['nullable', 'integer', 'exists:attendance_absence_reasons,id'],
            'is_justified' => ['nullable', 'boolean'],
            'minutes_late' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'early_departure' => ['nullable', 'boolean'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $attendanceRecord->getAttributes();
        $attendanceRecord->update([
            ...$data,
            'corrected_at' => now(),
            'origin' => 'manual',
            'updated_by' => $request->user()?->id,
        ]);
        $alerts->rebuild($attendanceRecord->academic_year_id, $attendanceRecord->course_section_id);
        $this->statisticsAudit->log('attendance_record_corrected', $attendanceRecord, $request->user(), $before, $attendanceRecord->fresh()->getAttributes(), $data['correction_reason'] ?? null, $request);
        $this->statisticsCache->invalidate();

        return response()->json($attendanceRecord->fresh());
    }

    public function followup(
        StoreAttendanceFollowupRequest $request,
        AttendanceAlert $attendanceAlert,
    ): JsonResponse {
        $followup = $attendanceAlert->followups()->create([
            ...$request->validated(),
            'status' => $request->validated('status', 'completed'),
            'created_by' => $request->user()?->id,
        ]);
        if ($attendanceAlert->status === 'open') {
            $attendanceAlert->update(['status' => 'in_progress']);
        }
        $this->statisticsCache->invalidate();

        return response()->json($followup->load('createdBy:id,name'), 201);
    }

    public function updateProjection(
        UpdateAttendanceProjectionRequest $request,
        AcademicYear $academicYear,
    ): JsonResponse {
        $settings = AttendanceProjectionSetting::query()->updateOrCreate(
            ['academic_year_id' => $academicYear->id],
            [...$request->validated(), 'updated_by' => $request->user()?->id],
        );
        $this->statisticsCache->invalidate();

        return response()->json($settings);
    }

    private function importPayload(AttendanceImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'filename' => $import->original_filename,
            'checksum' => $import->checksum,
            'course' => $import->courseSection?->display_name,
            'academic_year' => $import->academicYear?->name,
            'parsed_students' => $import->parsed_students,
            'matched_students' => $import->matched_students,
            'unmatched_students' => $import->unmatched_students,
            'imported_records' => $import->imported_records,
            'conflict_records' => $import->conflict_records,
            'preview' => $import->preview_payload,
            'validation' => $import->validation_payload,
            'confirmed_at' => $import->confirmed_at?->toIso8601String(),
        ];
    }
}
