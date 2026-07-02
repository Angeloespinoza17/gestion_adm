<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreCourseSectionRequest;
use App\Http\Requests\Students\UpdateCourseSectionRequest;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $academicYearId = $request->query('academic_year_id')
            ?: AcademicYear::query()->where('is_active', true)->value('id');

        $query = CourseSection::query()
            ->with([
                'academicYear:id,name,year,is_active,is_closed',
                'educationLevel:id,name,order,type',
            ])
            ->withCount([
                'enrollments as enrollments_count' => fn ($builder) => $builder
                    ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES),
            ])
            ->when($academicYearId, fn ($builder) => $builder->where('academic_year_id', $academicYearId))
            ->orderBy('education_level_id')
            ->orderBy('section_name');

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(StoreCourseSectionRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $academicYear = AcademicYear::query()->findOrFail($payload['academic_year_id']);
        $this->ensureYearEditable($academicYear, $request->user()?->isSuperAdmin() ?? false);

        $educationLevel = EducationLevel::query()->findOrFail($payload['education_level_id']);
        $payload['display_name'] = CourseSection::makeDisplayName($educationLevel, $payload['section_name']);
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $courseSection = CourseSection::query()->create($payload);

        return response()->json([
            'message' => 'Curso creado correctamente.',
            'data' => $courseSection->load('academicYear:id,name,year,is_active,is_closed', 'educationLevel:id,name,order,type'),
        ], 201);
    }

    public function show(CourseSection $courseSection): JsonResponse
    {
        return response()->json([
            'data' => $courseSection->load([
                'academicYear:id,name,year,is_active,is_closed',
                'educationLevel:id,name,order,type',
                'enrollments' => fn ($query) => $query
                    ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                    ->with('studentProfile.user:id,student_id,name,email,active')
                    ->orderBy('snapshot_course_display_name')
                    ->orderBy('id'),
            ]),
        ]);
    }

    public function update(UpdateCourseSectionRequest $request, CourseSection $courseSection): JsonResponse
    {
        $academicYearId = $request->validated()['academic_year_id'] ?? $courseSection->academic_year_id;
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $this->ensureYearEditable($academicYear, $request->user()?->isSuperAdmin() ?? false);

        $payload = $request->validated();
        $educationLevelId = $payload['education_level_id'] ?? $courseSection->education_level_id;
        $sectionName = $payload['section_name'] ?? $courseSection->section_name;
        $educationLevel = EducationLevel::query()->findOrFail($educationLevelId);

        $payload['display_name'] = CourseSection::makeDisplayName($educationLevel, $sectionName);
        $payload['updated_by'] = $request->user()?->id;

        $courseSection->update($payload);

        return response()->json([
            'message' => 'Curso actualizado correctamente.',
            'data' => $courseSection->fresh()->load('academicYear:id,name,year,is_active,is_closed', 'educationLevel:id,name,order,type'),
        ]);
    }

    private function ensureYearEditable(AcademicYear $academicYear, bool $isSuperAdmin): void
    {
        if ($academicYear->is_closed && !$isSuperAdmin) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'No puedes modificar cursos de un año académico cerrado.',
            ]);
        }
    }
}
