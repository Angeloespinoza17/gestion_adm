<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ImportStudentPdfChunkRequest;
use App\Http\Requests\Students\ImportStudentPdfRequest;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentMovement;
use App\Models\StudentProfile;
use App\Models\StudentPromotion;
use App\Services\Students\StudentAccountService;
use App\Services\Students\StudentPdfChunkUploadService;
use App\Services\Students\StudentPdfImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentAccountService $studentAccountService,
    ) {}

    public function catalogs(): JsonResponse
    {
        $academicYears = AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active', 'is_closed']);
        $activeYear = $academicYears->firstWhere('is_active', true);

        return response()->json([
            'academic_years' => $academicYears,
            'active_academic_year_id' => $activeYear?->id,
            'education_levels' => EducationLevel::query()->orderBy('order')->get(['id', 'name', 'order', 'type']),
            'general_statuses' => StudentProfile::GENERAL_STATUS_OPTIONS,
            'enrollment_statuses' => StudentEnrollment::STATUS_OPTIONS,
            'movement_types' => StudentEnrollmentMovement::TYPE_OPTIONS,
            'promotion_statuses' => StudentPromotion::STATUS_OPTIONS,
            'section_names' => ['A', 'B', 'C'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->resolveListFilters($request);
        $students = $this->filteredStudentsQuery($request)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate((int) $request->query('per_page', 15));

        $students->setCollection($this->decorateStudentList($students->getCollection(), $filters));

        return response()->json($students);
    }

    public function export(Request $request): JsonResponse
    {
        $filters = $this->resolveListFilters($request);
        $students = $this->filteredStudentsQuery($request)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'data' => $this->decorateStudentList($students, $filters)->values(),
        ]);
    }

    public function importPdf(ImportStudentPdfRequest $request, StudentPdfImportService $importService): JsonResponse
    {
        return $this->studentPdfImportResponse(
            fn () => $importService->import(
                $request->file('pdf'),
                $request->user(),
                $request->integer('course_section_id') ?: null,
            ),
        );
    }

    public function importPdfChunk(
        ImportStudentPdfChunkRequest $request,
        StudentPdfChunkUploadService $chunkUploadService,
        StudentPdfImportService $importService,
    ): JsonResponse {
        try {
            $chunk = $chunkUploadService->receive(
                (int) $request->user()->id,
                $request->validated(),
                $request->getContent(),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'pdf' => [$exception->getMessage()],
            ]);
        }

        if (! $chunk['completed']) {
            return response()->json([
                'message' => 'Fragmento recibido.',
                'data' => $chunk,
            ]);
        }

        try {
            return $this->studentPdfImportResponse(
                fn () => $importService->importPath(
                    $chunk['path'],
                    $request->user(),
                    $request->integer('course_section_id') ?: null,
                ),
            );
        } finally {
            $chunkUploadService->cleanup($chunk['directory']);
        }
    }

    private function studentPdfImportResponse(callable $import): JsonResponse
    {
        try {
            $result = $import();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'pdf' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'message' => 'Importación de estudiantes finalizada.',
            'data' => $result,
        ]);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $accountPayload = [
            'account_active' => $payload['account_active'] ?? true,
            'password' => $payload['password'] ?? null,
        ];

        unset($payload['account_active'], $payload['password']);

        $student = $this->studentAccountService->store($payload, $accountPayload, $request->user());

        return response()->json([
            'message' => 'Estudiante creada correctamente.',
            'data' => $this->loadStudent($student),
        ], 201);
    }

    public function show(StudentProfile $studentProfile): JsonResponse
    {
        return response()->json([
            'data' => $this->loadStudent($studentProfile),
        ]);
    }

    public function update(UpdateStudentRequest $request, StudentProfile $studentProfile): JsonResponse
    {
        $payload = $request->validated();
        $accountPayload = [];

        if (array_key_exists('account_active', $payload)) {
            $accountPayload['account_active'] = $payload['account_active'];
            unset($payload['account_active']);
        }

        if (array_key_exists('password', $payload)) {
            $accountPayload['password'] = $payload['password'];
            unset($payload['password']);
        }

        $student = $this->studentAccountService->update($studentProfile, $payload, $accountPayload, $request->user());

        return response()->json([
            'message' => 'Estudiante actualizada correctamente.',
            'data' => $this->loadStudent($student),
        ]);
    }

    private function loadStudent(StudentProfile $student): StudentProfile
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $student->load([
            'user:id,student_id,name,email,active',
            'user.roles:id,name,slug',
            'enrollments:id,student_profile_id,academic_year_id,course_section_id,enrollment_status,registration_number,enrolled_at,withdrawn_at,observations,snapshot_year_name,snapshot_level_name,snapshot_section_name,snapshot_course_display_name,created_by,updated_by,created_at,updated_at',
            'enrollments.academicYear:id,name,year,is_active,is_closed',
            'enrollments.courseSection:id,academic_year_id,education_level_id,section_name,display_name',
            'enrollments.courseSection.educationLevel:id,name,order,type',
            'promotions:id,student_profile_id,from_academic_year_id,to_academic_year_id,from_course_section_id,to_course_section_id,promotion_status,notes,created_by,created_at',
            'promotions.fromAcademicYear:id,name,year',
            'promotions.toAcademicYear:id,name,year',
            'promotions.fromCourseSection:id,display_name',
            'promotions.toCourseSection:id,display_name',
            'enrollmentMovements:id,student_enrollment_id,student_profile_id,academic_year_id,from_course_section_id,to_course_section_id,movement_type,effective_date,from_status,to_status,notes,snapshot_year_name,snapshot_from_course_display_name,snapshot_to_course_display_name,created_by,updated_by,created_at,updated_at',
            'enrollmentMovements.fromCourseSection:id,display_name',
            'enrollmentMovements.toCourseSection:id,display_name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        $student->setAttribute('current_enrollment', $student->preferredEnrollment($activeYear));
        $student->setAttribute('latest_enrollment', $student->latestEnrollment());

        return $student;
    }

    private function filteredStudentsQuery(Request $request): Builder
    {
        $filters = $this->resolveListFilters($request);

        return StudentProfile::query()
            ->with([
                'user:id,student_id,name,email,active',
                'enrollments' => fn ($query) => $query
                    ->with([
                        'academicYear:id,name,year,is_active',
                        'courseSection:id,academic_year_id,education_level_id,section_name,display_name',
                        'courseSection.educationLevel:id,name,order,type',
                    ])
                    ->orderByDesc('academic_year_id')
                    ->orderByDesc('id'),
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query
                        ->where('first_name', 'like', "%{$filters['search']}%")
                        ->orWhere('last_name', 'like', "%{$filters['search']}%")
                        ->orWhere('rut', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%");
                });
            })
            ->when($filters['general_status'] !== '', fn ($query) => $query->where('general_status', $filters['general_status']))
            ->when($filters['has_enrollment_filters'], function ($query) use ($filters) {
                $query->whereHas('enrollments', function ($enrollmentQuery) use ($filters) {
                    $enrollmentQuery
                        ->when($filters['academic_year_id'], fn ($query) => $query->where('academic_year_id', $filters['academic_year_id']))
                        ->when($filters['course_section_id'], fn ($query) => $query->where('course_section_id', $filters['course_section_id']))
                        ->when($filters['education_level_id'] || $filters['section_name'] !== '', function ($query) use ($filters) {
                            $query->whereHas('courseSection', function ($courseQuery) use ($filters) {
                                $courseQuery
                                    ->when($filters['education_level_id'], fn ($query) => $query->where('education_level_id', $filters['education_level_id']))
                                    ->when($filters['section_name'] !== '', fn ($query) => $query->where('section_name', $filters['section_name']));
                            });
                        });
                });
            });
    }

    private function resolveListFilters(Request $request): array
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $academicYearId = $request->query('academic_year_id') ?: $activeYear?->id;
        $courseSectionId = $request->query('course_section_id');
        $educationLevelId = $request->query('education_level_id');
        $sectionName = trim((string) $request->query('section_name'));
        $generalStatus = trim((string) $request->query('general_status'));
        $search = trim((string) $request->query('search'));

        return [
            'active_year' => $activeYear,
            'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
            'course_section_id' => $courseSectionId ? (int) $courseSectionId : null,
            'education_level_id' => $educationLevelId ? (int) $educationLevelId : null,
            'section_name' => $sectionName,
            'general_status' => $generalStatus,
            'search' => $search,
            'has_enrollment_filters' => $academicYearId || $courseSectionId || $educationLevelId || $sectionName !== '',
        ];
    }

    private function decorateStudentList(Collection $students, array $filters): Collection
    {
        return $students->map(function (StudentProfile $student) use ($filters) {
            $selectedEnrollment = $student->matchingEnrollment(
                $filters['academic_year_id'],
                $filters['course_section_id'],
                $filters['education_level_id'],
                $filters['section_name'] !== '' ? $filters['section_name'] : null,
            );
            $currentEnrollment = $student->preferredEnrollment($filters['active_year']);
            $latestEnrollment = $student->latestEnrollment();

            $student->setAttribute('selected_enrollment', $selectedEnrollment);
            $student->setAttribute('current_enrollment', $currentEnrollment);
            $student->setAttribute('latest_enrollment', $latestEnrollment);

            return $student;
        });
    }
}
