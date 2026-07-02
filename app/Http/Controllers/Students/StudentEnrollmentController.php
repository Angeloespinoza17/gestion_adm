<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentEnrollmentRequest;
use App\Http\Requests\Students\UpdateStudentEnrollmentRequest;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Services\Students\StudentEnrollmentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentController extends Controller
{
    public function __construct(
        private readonly StudentEnrollmentLifecycleService $lifecycleService,
    ) {
    }

    public function store(StoreStudentEnrollmentRequest $request, StudentProfile $studentProfile): JsonResponse
    {
        $payload = $request->validated();
        $academicYear = AcademicYear::query()->findOrFail($payload['academic_year_id']);
        $courseSection = CourseSection::query()->with('educationLevel')->findOrFail($payload['course_section_id']);

        $this->ensureYearEditable($academicYear, $request->user()?->isSuperAdmin() ?? false);
        $this->ensureSectionMatchesYear($courseSection, $academicYear);
        $this->ensureUniqueEnrollment($studentProfile, $academicYear);

        $enrollment = StudentEnrollment::query()->create(array_merge($payload, [
            'student_profile_id' => $studentProfile->id,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ], StudentEnrollment::snapshotPayload($academicYear, $courseSection)));

        $this->lifecycleService->ensureInitialMovement($enrollment, $request->user(), 'Matrícula anual creada.');
        $this->lifecycleService->syncStudentGeneralStatus($enrollment->fresh(['academicYear', 'studentProfile']), $request->user());

        return response()->json([
            'message' => 'Matrícula anual creada correctamente.',
            'data' => $enrollment->load('academicYear:id,name,year,is_active,is_closed', 'courseSection.educationLevel:id,name,order,type'),
        ], 201);
    }

    public function update(UpdateStudentEnrollmentRequest $request, StudentEnrollment $studentEnrollment): JsonResponse
    {
        $payload = $request->validated();
        $studentEnrollment->loadMissing('academicYear', 'courseSection.educationLevel', 'studentProfile');
        $academicYear = $studentEnrollment->academicYear()->firstOrFail();
        $this->ensureYearEditable($academicYear, $request->user()?->isSuperAdmin() ?? false);

        $currentStatus = $studentEnrollment->enrollment_status;
        $newStatus = $payload['enrollment_status'] ?? $currentStatus;

        if ($currentStatus === 'retirada' && $newStatus !== 'retirada') {
            $courseSection = isset($payload['course_section_id'])
                ? CourseSection::query()->with('educationLevel')->findOrFail($payload['course_section_id'])
                : $studentEnrollment->courseSection;

            $updated = $this->lifecycleService->reenter($studentEnrollment, $courseSection, [
                'effective_date' => $payload['enrolled_at'] ?? now()->format('Y-m-d'),
                'enrollment_status' => $newStatus,
                'notes' => $payload['observations'] ?? null,
            ], $request->user());

            return response()->json([
                'message' => 'Reingreso registrado correctamente.',
                'data' => $updated->load('academicYear:id,name,year,is_active,is_closed', 'courseSection.educationLevel:id,name,order,type'),
            ]);
        }

        if ($newStatus === 'retirada' && $currentStatus !== 'retirada') {
            $updated = $this->lifecycleService->withdraw($studentEnrollment, [
                'effective_date' => $payload['withdrawn_at'] ?? now()->format('Y-m-d'),
                'notes' => $payload['observations'] ?? null,
            ], $request->user());

            return response()->json([
                'message' => 'Retiro registrado correctamente.',
                'data' => $updated->load('academicYear:id,name,year,is_active,is_closed', 'courseSection.educationLevel:id,name,order,type'),
            ]);
        }

        if (isset($payload['course_section_id']) && (int) $payload['course_section_id'] !== (int) $studentEnrollment->course_section_id) {
            $courseSection = CourseSection::query()->with('educationLevel')->findOrFail($payload['course_section_id']);
            $updated = $this->lifecycleService->transfer($studentEnrollment, $courseSection, [
                'effective_date' => now()->format('Y-m-d'),
                'notes' => $payload['observations'] ?? null,
            ], $request->user());

            if (isset($payload['enrollment_status']) && $payload['enrollment_status'] !== $updated->enrollment_status) {
                $updated->update([
                    'enrollment_status' => $payload['enrollment_status'],
                    'updated_by' => $request->user()?->id,
                ]);
                $this->lifecycleService->syncStudentGeneralStatus($updated->fresh(['academicYear', 'studentProfile']), $request->user());
            }

            return response()->json([
                'message' => 'Cambio de curso registrado correctamente.',
                'data' => $updated->fresh()->load('academicYear:id,name,year,is_active,is_closed', 'courseSection.educationLevel:id,name,order,type'),
            ]);
        }

        $payload['updated_by'] = $request->user()?->id;
        $studentEnrollment->update($payload);
        $this->lifecycleService->ensureInitialMovement($studentEnrollment, $request->user(), 'Bitácora inicial de matrícula.');
        $this->lifecycleService->syncStudentGeneralStatus($studentEnrollment->fresh(['academicYear', 'studentProfile']), $request->user());

        return response()->json([
            'message' => 'Matrícula anual actualizada correctamente.',
            'data' => $studentEnrollment->fresh()->load('academicYear:id,name,year,is_active,is_closed', 'courseSection.educationLevel:id,name,order,type'),
        ]);
    }

    private function ensureYearEditable(AcademicYear $academicYear, bool $isSuperAdmin): void
    {
        if ($academicYear->is_closed && !$isSuperAdmin) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'No puedes modificar matrículas de un año académico cerrado.',
            ]);
        }
    }

    private function ensureSectionMatchesYear(CourseSection $courseSection, AcademicYear $academicYear): void
    {
        if ((int) $courseSection->academic_year_id !== (int) $academicYear->id) {
            throw ValidationException::withMessages([
                'course_section_id' => 'El curso seleccionado no pertenece al año académico indicado.',
            ]);
        }
    }

    private function ensureUniqueEnrollment(StudentProfile $studentProfile, AcademicYear $academicYear): void
    {
        if (StudentEnrollment::query()
            ->where('student_profile_id', $studentProfile->id)
            ->where('academic_year_id', $academicYear->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'La estudiante ya tiene una matrícula registrada para este año académico.',
            ]);
        }
    }
}
