<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\Inspectoria\InspectoriaPass;
use App\Models\StudentProfile;
use App\Services\Inspectoria\InspectoriaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectoriaStudentController extends Controller
{
    public function __construct(private readonly InspectoriaAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::STUDENTS), 403);
        $user = $request->user();
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $courseScoped = $this->access->isCourseScoped($user);
        $assignedCourseIds = $courseScoped ? $this->access->assignedCourseIds($user) : collect();
        $search = trim((string) $request->query('search'));
        $students = StudentProfile::query()->with(['enrollments' => fn ($q) => $q
            ->when($courseScoped, fn ($inner) => $inner->whereIn('course_section_id', $assignedCourseIds))
            ->with(['courseSection:id,display_name', 'academicYear:id,name,year,is_active'])])
            ->withCount(['enrollments'])
            ->when($courseScoped, fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                ->whereIn('course_section_id', $assignedCourseIds)
                ->when($activeYear, fn (Builder $inner) => $inner->where('academic_year_id', $activeYear->id))))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('registered_name', 'like', "%{$search}%")->orWhere('rut', 'like', "%{$search}%");
                });
            })->when($request->filled('course_section_id'), function (Builder $query) use ($request, $activeYear) {
                $query->whereHas('enrollments', fn ($enrollments) => $enrollments
                    ->where('course_section_id', $request->query('course_section_id'))
                    ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id)));
            })->orderBy('last_name')->orderBy('first_name')->paginate((int) $request->query('per_page', 18));

        $students->setCollection($students->getCollection()->map(function (StudentProfile $student) use ($activeYear) {
            $enrollment = $student->preferredEnrollment($activeYear);

            return [
                'id' => $student->id, 'name' => $student->registered_name_resolved, 'rut' => $student->rut,
                'status' => $student->general_status, 'phone' => $student->phone,
                'guardian_name' => $student->guardian_name, 'guardian_phone' => $student->guardian_phone,
                'course' => $enrollment?->snapshot_course_display_name ?? $enrollment?->courseSection?->display_name,
                'course_section_id' => $enrollment?->course_section_id,
            ];
        }));

        return response()->json($students);
    }

    public function show(Request $request, StudentProfile $student): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::STUDENTS), 403);
        $user = $request->user();
        abort_unless($this->access->canAccessStudent($user, $student->id), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');
        $courseScoped = $this->access->isCourseScoped($user);
        $assignedCourseIds = $courseScoped ? $this->access->assignedCourseIds($user) : collect();
        $student->load(['enrollments' => fn ($query) => $query
            ->when($courseScoped, fn ($inner) => $inner->whereIn('course_section_id', $assignedCourseIds))
            ->with(['academicYear:id,name,year,is_active', 'courseSection:id,display_name,academic_year_id'])]);
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $student->setAttribute('current_enrollment', $student->preferredEnrollment($activeYear));

        $attentions = InspectoriaAttention::query()->where('student_profile_id', $student->id)->with('attendedBy:id,name');
        $passes = InspectoriaPass::query()->where('student_profile_id', $student->id)->with('issuedBy:id,name');
        $dailyLogs = InspectoriaDailyLog::query()->where('student_profile_id', $student->id)->with('registeredBy:id,name');
        $this->access->scopeToAssignedCourses($attentions, $user);
        $this->access->scopeToAssignedCourses($passes, $user);
        $this->access->scopeDailyLogs($dailyLogs, $user);

        return response()->json(['data' => [
            'student' => $student,
            'attentions' => $attentions->latest('attended_at')->limit(30)->get(),
            'passes' => $passes->latest('issued_at')->limit(30)->get(),
            'daily_logs' => $dailyLogs->latest('happened_at')->limit(30)->get(),
        ]]);
    }
}
