<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\Inspectoria\InspectoriaPass;
use App\Models\Inspectoria\InspectoriaPickupRestriction;
use App\Models\PorterStudentWithdrawal;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Services\Inspectoria\InspectoriaAccessService;
use App\Services\Inspectoria\InspectoriaPsychosocialProfessionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectoriaCatalogController extends Controller
{
    public function __construct(
        private readonly InspectoriaAccessService $access,
        private readonly InspectoriaPsychosocialProfessionalService $psychosocialProfessionals,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $user = $request->user();
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $courseScoped = $this->access->isCourseScoped($user);
        $assignedCourseIds = $courseScoped ? $this->access->assignedCourseIds($user) : collect();

        $courses = CourseSection::query()->withCount('enrollments')->orderBy('display_name')
            ->when($courseScoped, fn ($query) => $query->whereIn('id', $assignedCourseIds))
            ->get(['id', 'academic_year_id', 'display_name', 'section_name']);

        $students = StudentProfile::query()
            ->with(['enrollments' => fn ($query) => $query
                ->when($courseScoped, fn ($inner) => $inner->whereIn('course_section_id', $assignedCourseIds))
                ->with(['courseSection:id,display_name', 'academicYear:id,name,year,is_active'])])
            ->when($courseScoped, fn ($query) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments
                ->whereIn('course_section_id', $assignedCourseIds)
                ->when($activeYear, fn ($inner) => $inner->where('academic_year_id', $activeYear->id))))
            ->orderBy('last_name')->orderBy('first_name')->limit(1000)->get()
            ->map(function (StudentProfile $student) use ($activeYear) {
                $enrollment = $student->preferredEnrollment($activeYear);

                return [
                    'id' => $student->id,
                    'name' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'course' => $enrollment?->snapshot_course_display_name ?? $enrollment?->courseSection?->display_name,
                    'course_section_id' => $enrollment?->course_section_id,
                    'status' => $student->general_status,
                    'guardian_name' => $student->guardian_name,
                    'guardian_rut' => $student->guardian_rut,
                    'guardian_phone' => $student->guardian_phone,
                    'guardian_relationship' => $student->guardian_relationship ?: $student->guardian_role,
                    'guardian_backup_name' => $student->guardian_backup_name,
                    'guardian_backup_rut' => $student->guardian_backup_rut,
                    'guardian_backup_phone' => $student->guardian_backup_phone,
                    'guardian_backup_relationship' => $student->guardian_backup_relationship ?: $student->guardian_backup_role,
                ];
            });

        $inspectors = Staff::query()
            ->where('active', true)
            ->when($courseScoped, fn ($query) => $query->whereKey($user->staff_id))
            ->where(function ($query) {
                $query->whereHas('cargo', fn ($cargo) => $cargo->where('slug', 'inspectoria'))
                    ->orWhereHas('user.roles', fn ($roles) => $roles->where('slug', 'inspectoria'));
            })
            ->orderBy('full_name')->get(['id', 'full_name', 'rut', 'cargo_id']);

        return response()->json([
            'academic_years' => AcademicYear::query()
                ->when($courseScoped, fn ($query) => $query->whereIn('id', $courses->pluck('academic_year_id')))
                ->ordered()->get(['id', 'name', 'year', 'is_active']),
            'active_academic_year_id' => $activeYear?->id,
            'courses' => $courses,
            'students' => $students,
            'inspectors' => $inspectors,
            'request_types' => $this->options(InspectoriaAttention::REQUEST_TYPES),
            'attention_actions' => $this->options(InspectoriaAttention::ACTIONS),
            'psychosocial_professionals' => $this->psychosocialProfessionals->options(),
            'destinations' => $this->options(InspectoriaPass::DESTINATIONS),
            'log_categories' => $this->options(InspectoriaDailyLog::CATEGORIES),
            'withdrawal_statuses' => PorterStudentWithdrawal::STATUS_OPTIONS,
            'withdrawal_reasons' => PorterStudentWithdrawal::REASON_OPTIONS,
            'withdrawal_relationships' => PorterStudentWithdrawal::RELATIONSHIP_OPTIONS,
            'pickup_restriction_types' => $this->options(InspectoriaPickupRestriction::TYPES),
            'capabilities' => [
                'manage_attentions' => $this->access->can($request->user(), InspectoriaAccessService::ATTENTIONS),
                'manage_assignments' => $this->access->can($request->user(), InspectoriaAccessService::ASSIGNMENTS),
                'manage_passes' => $this->access->can($request->user(), InspectoriaAccessService::PASSES),
                'view_students' => $this->access->can($request->user(), InspectoriaAccessService::STUDENTS),
                'view_withdrawals' => $this->access->can($request->user(), InspectoriaAccessService::WITHDRAWALS),
                'manage_pickup_restrictions' => $this->access->can($request->user(), InspectoriaAccessService::RESTRICTIONS),
                'manage_daily_log' => $this->access->can($request->user(), InspectoriaAccessService::DAILY_LOG),
            ],
        ]);
    }

    private function options(array $items): array
    {
        return collect($items)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
