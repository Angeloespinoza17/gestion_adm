<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterStudentContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PorterStudentController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
        private readonly PorterStudentContextService $studentContextService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $activeYear = $this->studentContextService->activeAcademicYear();
        $search = trim((string) $request->query('search'));
        $courseSectionId = $request->query('course_section_id');
        $educationLevelId = $request->query('education_level_id');
        $status = trim((string) $request->query('status'));

        $students = StudentProfile::query()
            ->with([
                'enrollments' => fn ($query) => $query
                    ->when($activeYear, fn ($query) => $query->where('academic_year_id', $activeYear->id))
                    ->with([
                        'academicYear:id,name,year,is_active',
                        'courseSection:id,academic_year_id,education_level_id,section_name,display_name',
                        'courseSection.educationLevel:id,name,order,type',
                    ]),
                'porterWithdrawals' => fn ($query) => $query->latest('withdrawn_at')->limit(3),
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('registered_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhere('guardian_name', 'like', "%{$search}%")
                        ->orWhere('guardian_backup_name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('general_status', $status))
            ->when($courseSectionId || $educationLevelId || $activeYear, function (Builder $query) use ($courseSectionId, $educationLevelId, $activeYear) {
                $query->whereHas('enrollments', function (Builder $enrollmentQuery) use ($courseSectionId, $educationLevelId, $activeYear) {
                    if ($activeYear) {
                        $enrollmentQuery->where('academic_year_id', $activeYear->id);
                    }

                    if ($courseSectionId) {
                        $enrollmentQuery->where('course_section_id', $courseSectionId);
                    }

                    if ($educationLevelId) {
                        $enrollmentQuery->whereHas('courseSection', fn (Builder $courseQuery) => $courseQuery->where('education_level_id', $educationLevelId));
                    }
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate((int) $request->query('per_page', 12));

        $students->setCollection(
            $students->getCollection()->map(function (StudentProfile $student) use ($activeYear) {
                $currentEnrollment = $this->studentContextService->currentEnrollment($student, $activeYear);
                $payload = $this->studentContextService->porterStudentPayload($student, $currentEnrollment);
                $payload['recent_withdrawals'] = $student->porterWithdrawals->map(fn ($withdrawal) => [
                    'id' => $withdrawal->id,
                    'withdrawn_at' => $withdrawal->withdrawn_at,
                    'status' => $withdrawal->status,
                    'person_name' => $withdrawal->person_name,
                    'reason' => $withdrawal->reason,
                ])->values();

                return $payload;
            })
        );

        return response()->json($students);
    }

    public function show(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $studentProfile->load([
            'porterWithdrawals' => fn ($query) => $query
                ->with(['registeredBy:id,name', 'authorizedBy:id,name'])
                ->latest('withdrawn_at')
                ->limit(20),
        ]);

        $payload = $this->studentContextService->porterStudentPayload($studentProfile);
        $payload['withdrawal_history'] = $studentProfile->porterWithdrawals->map(fn ($withdrawal) => [
            'id' => $withdrawal->id,
            'withdrawn_at' => $withdrawal->withdrawn_at,
            'status' => $withdrawal->status,
            'person_name' => $withdrawal->person_name,
            'reason' => $withdrawal->reason,
            'registered_by' => $withdrawal->registeredBy?->name,
            'authorized_by' => $withdrawal->authorizedBy?->name,
            'observations' => $withdrawal->observations,
        ])->values();

        return response()->json([
            'data' => $payload,
        ]);
    }
}
