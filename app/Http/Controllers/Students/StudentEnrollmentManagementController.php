<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ReenterStudentEnrollmentRequest;
use App\Http\Requests\Students\TransferStudentEnrollmentRequest;
use App\Http\Requests\Students\WithdrawStudentEnrollmentRequest;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Services\Students\StudentEnrollmentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentEnrollmentManagementController extends Controller
{
    public function __construct(
        private readonly StudentEnrollmentLifecycleService $lifecycleService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $academicYearId = $request->query('academic_year_id') ?: $activeYearId;
        $courseSectionId = $request->query('course_section_id');
        $status = trim((string) $request->query('enrollment_status'));
        $search = trim((string) $request->query('search'));

        $baseQuery = StudentEnrollment::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->when($status !== '', fn ($query) => $query->where('enrollment_status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('studentProfile', function ($studentQuery) use ($search) {
                    $studentQuery->where(function ($studentQuery) use ($search) {
                        $studentQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('rut', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            });

        $enrollments = (clone $baseQuery)
            ->with([
                'academicYear:id,name,year,is_active,is_closed',
                'courseSection:id,academic_year_id,education_level_id,section_name,display_name,capacity,active',
                'courseSection.educationLevel:id,name,order,type',
                'studentProfile:id,first_name,last_name,rut,email,general_status',
                'studentProfile.user:id,student_id,name,email,active',
                'movements' => fn ($query) => $query
                    ->with([
                        'fromCourseSection:id,display_name',
                        'toCourseSection:id,display_name',
                    ])
                    ->orderByDesc('effective_date')
                    ->orderByDesc('id'),
            ])
            ->orderBy('snapshot_course_display_name')
            ->orderBy('id')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => $enrollments->items(),
            'current_page' => $enrollments->currentPage(),
            'last_page' => $enrollments->lastPage(),
            'per_page' => $enrollments->perPage(),
            'total' => $enrollments->total(),
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'retired' => (clone $baseQuery)->where('enrollment_status', 'retirada')->count(),
                'active' => (clone $baseQuery)->where('enrollment_status', '!=', 'retirada')->count(),
            ],
        ]);
    }

    public function transfer(TransferStudentEnrollmentRequest $request, StudentEnrollment $studentEnrollment): JsonResponse
    {
        $courseSection = CourseSection::query()->with('educationLevel')->findOrFail($request->validated()['course_section_id']);
        $updated = $this->lifecycleService->transfer($studentEnrollment, $courseSection, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Cambio de curso registrado correctamente.',
            'data' => $updated->load([
                'academicYear:id,name,year,is_active,is_closed',
                'courseSection:id,academic_year_id,education_level_id,section_name,display_name',
                'courseSection.educationLevel:id,name,order,type',
                'studentProfile:id,first_name,last_name,rut,email,general_status',
                'movements.fromCourseSection:id,display_name',
                'movements.toCourseSection:id,display_name',
            ]),
        ]);
    }

    public function withdraw(WithdrawStudentEnrollmentRequest $request, StudentEnrollment $studentEnrollment): JsonResponse
    {
        $updated = $this->lifecycleService->withdraw($studentEnrollment, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Retiro registrado correctamente.',
            'data' => $updated->load([
                'academicYear:id,name,year,is_active,is_closed',
                'courseSection:id,academic_year_id,education_level_id,section_name,display_name',
                'courseSection.educationLevel:id,name,order,type',
                'studentProfile:id,first_name,last_name,rut,email,general_status',
                'movements.fromCourseSection:id,display_name',
                'movements.toCourseSection:id,display_name',
            ]),
        ]);
    }

    public function reenter(ReenterStudentEnrollmentRequest $request, StudentEnrollment $studentEnrollment): JsonResponse
    {
        $courseSection = CourseSection::query()->with('educationLevel')->findOrFail($request->validated()['course_section_id']);
        $updated = $this->lifecycleService->reenter($studentEnrollment, $courseSection, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Reingreso registrado correctamente.',
            'data' => $updated->load([
                'academicYear:id,name,year,is_active,is_closed',
                'courseSection:id,academic_year_id,education_level_id,section_name,display_name',
                'courseSection.educationLevel:id,name,order,type',
                'studentProfile:id,first_name,last_name,rut,email,general_status',
                'movements.fromCourseSection:id,display_name',
                'movements.toCourseSection:id,display_name',
            ]),
        ]);
    }
}
