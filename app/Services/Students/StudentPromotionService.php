<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\StudentPromotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPromotionService
{
    private const CREATES_ENROLLMENT_STATUSES = [
        'promovida',
        'repitente',
        'cambio_paralelo',
    ];

    public function __construct(
        private readonly StudentEnrollmentLifecycleService $lifecycleService,
    ) {
    }

    public function promote(array $payload, ?User $actor = null): array
    {
        $fromAcademicYear = AcademicYear::query()->findOrFail($payload['from_academic_year_id']);
        $fromCourseSection = CourseSection::query()
            ->with('educationLevel')
            ->findOrFail($payload['from_course_section_id']);

        $toAcademicYear = !empty($payload['to_academic_year_id'])
            ? AcademicYear::query()->findOrFail($payload['to_academic_year_id'])
            : null;

        $defaultDestinationCourse = !empty($payload['to_course_section_id'])
            ? CourseSection::query()->with('educationLevel')->findOrFail($payload['to_course_section_id'])
            : null;

        if ((int) $fromCourseSection->academic_year_id !== (int) $fromAcademicYear->id) {
            throw ValidationException::withMessages([
                'from_course_section_id' => 'El curso origen no pertenece al año académico origen.',
            ]);
        }

        if ($toAcademicYear && $toAcademicYear->is_closed && !$actor?->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'to_academic_year_id' => 'No puedes promover estudiantes hacia un año académico cerrado.',
            ]);
        }

        if ($defaultDestinationCourse && $toAcademicYear && (int) $defaultDestinationCourse->academic_year_id !== (int) $toAcademicYear->id) {
            throw ValidationException::withMessages([
                'to_course_section_id' => 'El curso destino no pertenece al año académico destino.',
            ]);
        }

        return DB::transaction(function () use ($payload, $actor, $fromAcademicYear, $fromCourseSection, $toAcademicYear, $defaultDestinationCourse) {
            $summary = [];

            foreach ($payload['students'] as $index => $item) {
                $student = StudentProfile::query()->findOrFail($item['student_profile_id']);
                $status = $item['promotion_status'];
                $sourceEnrollment = StudentEnrollment::query()
                    ->where('student_profile_id', $student->id)
                    ->where('academic_year_id', $fromAcademicYear->id)
                    ->where('course_section_id', $fromCourseSection->id)
                    ->first();

                if (!$sourceEnrollment) {
                    throw ValidationException::withMessages([
                        "students.$index.student_profile_id" => "La estudiante {$student->full_name} no tiene matrícula en el curso origen seleccionado.",
                    ]);
                }

                $destinationCourse = null;
                $destinationEnrollment = null;

                if (in_array($status, self::CREATES_ENROLLMENT_STATUSES, true)) {
                    if (!$toAcademicYear) {
                        throw ValidationException::withMessages([
                            "students.$index.promotion_status" => 'Debes seleccionar un año académico destino para las promociones con matrícula.',
                        ]);
                    }

                    $destinationCourseId = $item['to_course_section_id'] ?? $defaultDestinationCourse?->id;
                    if (!$destinationCourseId) {
                        throw ValidationException::withMessages([
                            "students.$index.to_course_section_id" => "Debes seleccionar un curso destino para {$student->full_name}.",
                        ]);
                    }

                    $destinationCourse = CourseSection::query()->with('educationLevel')->findOrFail($destinationCourseId);

                    if ((int) $destinationCourse->academic_year_id !== (int) $toAcademicYear->id) {
                        throw ValidationException::withMessages([
                            "students.$index.to_course_section_id" => "El curso destino de {$student->full_name} no pertenece al año académico destino.",
                        ]);
                    }

                    if (StudentEnrollment::query()
                        ->where('student_profile_id', $student->id)
                        ->where('academic_year_id', $toAcademicYear->id)
                        ->exists()) {
                        throw ValidationException::withMessages([
                            "students.$index.student_profile_id" => "La estudiante {$student->full_name} ya tiene una matrícula registrada para {$toAcademicYear->name}.",
                        ]);
                    }

                    $destinationEnrollment = StudentEnrollment::query()->create(array_merge([
                        'student_profile_id' => $student->id,
                        'academic_year_id' => $toAcademicYear->id,
                        'course_section_id' => $destinationCourse->id,
                        'enrollment_status' => 'matriculada',
                        'enrolled_at' => $toAcademicYear->starts_at?->format('Y-m-d') ?: now()->format('Y-m-d'),
                        'observations' => $item['notes'] ?? null,
                        'created_by' => $actor?->id,
                        'updated_by' => $actor?->id,
                    ], StudentEnrollment::snapshotPayload($toAcademicYear, $destinationCourse)));

                    $this->lifecycleService->ensureInitialMovement($destinationEnrollment, $actor, 'Matrícula creada por promoción anual.');
                    $this->lifecycleService->syncStudentGeneralStatus($destinationEnrollment->fresh(['academicYear', 'studentProfile']), $actor);
                }

                StudentPromotion::query()->create([
                    'student_profile_id' => $student->id,
                    'from_academic_year_id' => $fromAcademicYear->id,
                    'to_academic_year_id' => $toAcademicYear?->id,
                    'from_course_section_id' => $fromCourseSection->id,
                    'to_course_section_id' => $destinationCourse?->id,
                    'promotion_status' => $status,
                    'notes' => $item['notes'] ?? null,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);

                $summary[] = [
                    'student_profile_id' => $student->id,
                    'student_name' => $student->full_name,
                    'promotion_status' => $status,
                    'to_course' => $destinationEnrollment?->snapshot_course_display_name,
                ];
            }

            return $summary;
        });
    }
}
