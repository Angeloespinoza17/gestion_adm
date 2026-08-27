<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspectoria\UpdateInspectoriaStudentProfileRequest;
use App\Models\AcademicYear;
use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\Inspectoria\InspectoriaPass;
use App\Models\StudentProfile;
use App\Services\Attendance\StudentMonthlyAttendanceContextService;
use App\Services\Inspectoria\InspectoriaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InspectoriaStudentController extends Controller
{
    public function __construct(
        private readonly InspectoriaAccessService $access,
        private readonly StudentMonthlyAttendanceContextService $attendanceContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::STUDENTS), 403);
        $user = $request->user();
        $currentAcademicYear = AcademicYear::query()->where('year', today()->year)->first();
        $courseScoped = $this->access->isCourseScoped($user);
        $assignedCourseIds = $courseScoped ? $this->access->assignedCourseIds($user) : collect();
        $search = trim((string) $request->query('search'));
        $students = StudentProfile::query()
            ->select(['id', 'first_name', 'last_name', 'registered_name', 'rut', 'general_status', 'phone', 'guardian_name', 'guardian_phone'])
            ->with(['enrollments' => fn ($q) => $q
                ->select(['id', 'student_profile_id', 'academic_year_id', 'course_section_id', 'enrollment_status', 'snapshot_course_display_name'])
                ->when($courseScoped, fn ($inner) => $inner->whereIn('course_section_id', $assignedCourseIds))
                ->with(['courseSection:id,display_name', 'academicYear:id,name,year,is_active'])])
            ->when($courseScoped, function (Builder $query) use ($assignedCourseIds, $currentAcademicYear) {
                if (! $currentAcademicYear) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                    ->whereIn('course_section_id', $assignedCourseIds)
                    ->where('academic_year_id', $currentAcademicYear->id));
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('registered_name', 'like', "%{$search}%")->orWhere('rut', 'like', "%{$search}%");
                });
            })->when($request->filled('course_section_id'), function (Builder $query) use ($request, $currentAcademicYear) {
                $query->whereHas('enrollments', fn ($enrollments) => $enrollments
                    ->where('course_section_id', $request->query('course_section_id'))
                    ->when($currentAcademicYear, fn ($q) => $q->where('academic_year_id', $currentAcademicYear->id)));
            })->orderBy('last_name')->orderBy('first_name')->paginate((int) $request->query('per_page', 18));

        $attendanceProfiles = $this->attendanceContext->forStudents($students->getCollection()->pluck('id'), $currentAcademicYear?->id);
        $students->setCollection($students->getCollection()->map(function (StudentProfile $student) use ($currentAcademicYear, $attendanceProfiles) {
            $enrollment = $student->preferredEnrollment($currentAcademicYear);

            return [
                'id' => $student->id, 'name' => $student->registered_name_resolved, 'rut' => $student->rut,
                'status' => $student->general_status, 'phone' => $student->phone,
                'guardian_name' => $student->guardian_name, 'guardian_phone' => $student->guardian_phone,
                'course' => $enrollment?->snapshot_course_display_name ?? $enrollment?->courseSection?->display_name,
                'course_section_id' => $enrollment?->course_section_id,
                'attendance_profile' => $attendanceProfiles->get($student->id),
            ];
        }));

        return response()->json($students);
    }

    public function show(Request $request, StudentProfile $student): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::STUDENTS), 403);
        abort_unless($this->access->canAccessStudent($request->user(), $student->id), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');

        return response()->json(['data' => $this->studentFileData($request, $student)]);
    }

    public function updateProfile(UpdateInspectoriaStudentProfileRequest $request, StudentProfile $student): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::EDIT_STUDENT_PROFILES), 403);
        abort_unless($this->access->canAccessStudent($request->user(), $student->id), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');

        $payload = $request->validated();
        $expectedUpdatedAt = Carbon::parse($payload['profile_updated_at']);
        unset($payload['profile_updated_at']);

        $changedFields = DB::transaction(function () use ($request, $student, $payload, $expectedUpdatedAt): array {
            $lockedStudent = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);

            if (! $lockedStudent->updated_at || ! $lockedStudent->updated_at->equalTo($expectedUpdatedAt)) {
                abort(409, 'La ficha fue actualizada por otra persona. Vuelve a abrirla antes de guardar tus cambios.');
            }

            $lockedStudent->fill($payload);
            $changedFields = array_keys($lockedStudent->getDirty());

            if ($changedFields === []) {
                return [];
            }

            $lockedStudent->forceFill(['updated_by' => $request->user()->id])->save();

            DB::table('inspectoria_student_profile_change_logs')->insert([
                'student_profile_id' => $lockedStudent->id,
                'actor_user_id' => $request->user()->id,
                'changed_fields' => json_encode($changedFields, JSON_THROW_ON_ERROR),
                'ip_address_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
                'user_agent_hash' => $request->userAgent() ? hash('sha256', (string) $request->userAgent()) : null,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $changedFields;
        });

        $student = StudentProfile::query()->findOrFail($student->id);

        return response()->json([
            'message' => $changedFields === []
                ? 'La ficha ya estaba actualizada.'
                : 'Datos de la ficha actualizados correctamente.',
            'data' => $this->studentFileData($request, $student),
            'changed_fields' => $changedFields,
        ]);
    }

    private function studentFileData(Request $request, StudentProfile $student): array
    {
        $user = $request->user();
        $courseScoped = $this->access->isCourseScoped($user);
        $assignedCourseIds = $courseScoped ? $this->access->assignedCourseIds($user) : collect();
        $student->load(['enrollments' => fn ($query) => $query
            ->when($courseScoped, fn ($inner) => $inner->whereIn('course_section_id', $assignedCourseIds))
            ->with(['academicYear:id,name,year,is_active', 'courseSection:id,display_name,academic_year_id']),
            'updatedBy:id,name',
        ]);
        $currentAcademicYear = AcademicYear::query()->where('year', today()->year)->first();
        $student->setAttribute('current_enrollment', $student->preferredEnrollment($currentAcademicYear));

        $attentions = InspectoriaAttention::query()->where('student_profile_id', $student->id)->with('attendedBy:id,name');
        $passes = InspectoriaPass::query()->where('student_profile_id', $student->id)->with('issuedBy:id,name');
        $dailyLogs = InspectoriaDailyLog::query()->where('student_profile_id', $student->id)->with('registeredBy:id,name');
        $this->access->scopeAttentions($attentions, $user);
        $this->access->scopeToAssignedCourses($passes, $user);
        $this->access->scopeDailyLogs($dailyLogs, $user);

        return [
            'student' => $student,
            'attendance_profile' => $this->attendanceContext->forStudent($student, $currentAcademicYear?->id),
            'attentions' => $attentions->latest('attended_at')->limit(30)->get(),
            'passes' => $passes->latest('issued_at')->limit(30)->get(),
            'daily_logs' => $dailyLogs->latest('happened_at')->limit(30)->get(),
        ];
    }
}
