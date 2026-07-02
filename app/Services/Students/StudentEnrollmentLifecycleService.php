<?php

namespace App\Services\Students;

use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentMovement;
use App\Models\User;
use App\Support\DateInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentLifecycleService
{
    public function recordInitialEnrollment(StudentEnrollment $enrollment, ?User $actor = null, ?string $notes = null): StudentEnrollmentMovement
    {
        $enrollment->loadMissing('academicYear', 'courseSection.educationLevel', 'studentProfile');

        return StudentEnrollmentMovement::query()->create(array_merge([
            'student_enrollment_id' => $enrollment->id,
            'student_profile_id' => $enrollment->student_profile_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'from_course_section_id' => null,
            'to_course_section_id' => $enrollment->course_section_id,
            'movement_type' => 'matricula',
            'effective_date' => $enrollment->enrolled_at?->format('Y-m-d'),
            'from_status' => null,
            'to_status' => $enrollment->enrollment_status,
            'notes' => $notes,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ], StudentEnrollmentMovement::snapshotPayload(
            $enrollment->academicYear,
            null,
            $enrollment->courseSection,
        )));
    }

    public function ensureInitialMovement(StudentEnrollment $enrollment, ?User $actor = null, ?string $notes = null): void
    {
        if (!$enrollment->movements()->exists()) {
            $this->recordInitialEnrollment($enrollment, $actor, $notes);
        }
    }

    public function transfer(StudentEnrollment $enrollment, CourseSection $destinationCourse, array $payload = [], ?User $actor = null): StudentEnrollment
    {
        return DB::transaction(function () use ($enrollment, $destinationCourse, $payload, $actor) {
            $enrollment->loadMissing('academicYear', 'courseSection.educationLevel', 'studentProfile');
            $this->ensureEnrollmentEditable($enrollment, $actor);
            $this->ensureSectionMatchesYear($enrollment, $destinationCourse);
            $this->ensureInitialMovement($enrollment, $actor);

            if ((int) $destinationCourse->id === (int) $enrollment->course_section_id) {
                throw ValidationException::withMessages([
                    'course_section_id' => 'Debes seleccionar un curso distinto para registrar el cambio.',
                ]);
            }

            if (in_array($enrollment->enrollment_status, ['retirada', 'egresada', 'trasladada'], true)) {
                throw ValidationException::withMessages([
                    'course_section_id' => 'No puedes cambiar de curso una matrícula retirada, egresada o trasladada desde este flujo.',
                ]);
            }

            $effectiveDate = $this->resolveEffectiveDate($payload['effective_date'] ?? null, $enrollment->enrolled_at?->format('Y-m-d'));

            if ($enrollment->enrolled_at && $effectiveDate < $enrollment->enrolled_at->format('Y-m-d')) {
                throw ValidationException::withMessages([
                    'effective_date' => 'La fecha del cambio de curso no puede ser anterior a la matrícula anual.',
                ]);
            }

            StudentEnrollmentMovement::query()->create(array_merge([
                'student_enrollment_id' => $enrollment->id,
                'student_profile_id' => $enrollment->student_profile_id,
                'academic_year_id' => $enrollment->academic_year_id,
                'from_course_section_id' => $enrollment->course_section_id,
                'to_course_section_id' => $destinationCourse->id,
                'movement_type' => 'cambio_curso',
                'effective_date' => $effectiveDate,
                'from_status' => $enrollment->enrollment_status,
                'to_status' => $enrollment->enrollment_status,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ], StudentEnrollmentMovement::snapshotPayload(
                $enrollment->academicYear,
                $enrollment->courseSection,
                $destinationCourse,
            )));

            $enrollment->update(array_merge([
                'course_section_id' => $destinationCourse->id,
                'withdrawn_at' => null,
                'updated_by' => $actor?->id,
            ], StudentEnrollment::snapshotPayload($enrollment->academicYear, $destinationCourse)));

            $updated = $enrollment->fresh(['academicYear', 'courseSection.educationLevel', 'studentProfile']);
            $this->syncStudentGeneralStatus($updated, $actor);

            return $updated;
        });
    }

    public function withdraw(StudentEnrollment $enrollment, array $payload = [], ?User $actor = null): StudentEnrollment
    {
        return DB::transaction(function () use ($enrollment, $payload, $actor) {
            $enrollment->loadMissing('academicYear', 'courseSection.educationLevel', 'studentProfile');
            $this->ensureEnrollmentEditable($enrollment, $actor);
            $this->ensureInitialMovement($enrollment, $actor);

            if ($enrollment->enrollment_status === 'retirada') {
                throw ValidationException::withMessages([
                    'student_enrollment_id' => 'La matrícula ya se encuentra retirada.',
                ]);
            }

            $effectiveDate = $this->resolveEffectiveDate($payload['effective_date'] ?? null, now()->format('Y-m-d'));

            if ($enrollment->enrolled_at && $effectiveDate < $enrollment->enrolled_at->format('Y-m-d')) {
                throw ValidationException::withMessages([
                    'effective_date' => 'La fecha de retiro no puede ser anterior a la matrícula anual.',
                ]);
            }

            StudentEnrollmentMovement::query()->create(array_merge([
                'student_enrollment_id' => $enrollment->id,
                'student_profile_id' => $enrollment->student_profile_id,
                'academic_year_id' => $enrollment->academic_year_id,
                'from_course_section_id' => $enrollment->course_section_id,
                'to_course_section_id' => null,
                'movement_type' => 'retiro',
                'effective_date' => $effectiveDate,
                'from_status' => $enrollment->enrollment_status,
                'to_status' => 'retirada',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ], StudentEnrollmentMovement::snapshotPayload(
                $enrollment->academicYear,
                $enrollment->courseSection,
                null,
            )));

            $enrollment->update([
                'enrollment_status' => 'retirada',
                'withdrawn_at' => $effectiveDate,
                'updated_by' => $actor?->id,
            ]);

            $updated = $enrollment->fresh(['academicYear', 'courseSection.educationLevel', 'studentProfile']);
            $this->syncStudentGeneralStatus($updated, $actor);

            return $updated;
        });
    }

    public function reenter(StudentEnrollment $enrollment, CourseSection $destinationCourse, array $payload = [], ?User $actor = null): StudentEnrollment
    {
        return DB::transaction(function () use ($enrollment, $destinationCourse, $payload, $actor) {
            $enrollment->loadMissing('academicYear', 'courseSection.educationLevel', 'studentProfile');
            $this->ensureEnrollmentEditable($enrollment, $actor);
            $this->ensureSectionMatchesYear($enrollment, $destinationCourse);
            $this->ensureInitialMovement($enrollment, $actor);

            if ($enrollment->enrollment_status !== 'retirada') {
                throw ValidationException::withMessages([
                    'student_enrollment_id' => 'Solo puedes reingresar matrículas retiradas.',
                ]);
            }

            $effectiveDate = $this->resolveEffectiveDate($payload['effective_date'] ?? null, now()->format('Y-m-d'));

            if ($enrollment->enrolled_at && $effectiveDate < $enrollment->enrolled_at->format('Y-m-d')) {
                throw ValidationException::withMessages([
                    'effective_date' => 'La fecha de reingreso no puede ser anterior a la matrícula anual.',
                ]);
            }

            $newStatus = $payload['enrollment_status'] ?? 'regular';
            if (!in_array($newStatus, ['matriculada', 'regular', 'suspendida'], true)) {
                throw ValidationException::withMessages([
                    'enrollment_status' => 'El reingreso solo permite estados activos o suspendidos.',
                ]);
            }

            StudentEnrollmentMovement::query()->create(array_merge([
                'student_enrollment_id' => $enrollment->id,
                'student_profile_id' => $enrollment->student_profile_id,
                'academic_year_id' => $enrollment->academic_year_id,
                'from_course_section_id' => $enrollment->course_section_id,
                'to_course_section_id' => $destinationCourse->id,
                'movement_type' => 'reingreso',
                'effective_date' => $effectiveDate,
                'from_status' => $enrollment->enrollment_status,
                'to_status' => $newStatus,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ], StudentEnrollmentMovement::snapshotPayload(
                $enrollment->academicYear,
                $enrollment->courseSection,
                $destinationCourse,
            )));

            $enrollment->update(array_merge([
                'course_section_id' => $destinationCourse->id,
                'enrollment_status' => $newStatus,
                'withdrawn_at' => null,
                'updated_by' => $actor?->id,
            ], StudentEnrollment::snapshotPayload($enrollment->academicYear, $destinationCourse)));

            $updated = $enrollment->fresh(['academicYear', 'courseSection.educationLevel', 'studentProfile']);
            $this->syncStudentGeneralStatus($updated, $actor);

            return $updated;
        });
    }

    public function syncStudentGeneralStatus(StudentEnrollment $enrollment, ?User $actor = null): void
    {
        $enrollment->loadMissing('academicYear', 'studentProfile');

        if (!$enrollment->academicYear?->is_active || !$enrollment->studentProfile) {
            return;
        }

        $generalStatus = match ($enrollment->enrollment_status) {
            'retirada' => 'retirado',
            'egresada' => 'egresado',
            'suspendida' => 'suspendido',
            default => 'activo',
        };

        if ($enrollment->studentProfile->general_status === $generalStatus) {
            return;
        }

        $enrollment->studentProfile->update([
            'general_status' => $generalStatus,
            'updated_by' => $actor?->id,
        ]);
    }

    private function ensureEnrollmentEditable(StudentEnrollment $enrollment, ?User $actor = null): void
    {
        if ($enrollment->academicYear?->is_closed && !$actor?->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'No puedes modificar matrículas de un año académico cerrado.',
            ]);
        }
    }

    private function ensureSectionMatchesYear(StudentEnrollment $enrollment, CourseSection $destinationCourse): void
    {
        if ((int) $destinationCourse->academic_year_id !== (int) $enrollment->academic_year_id) {
            throw ValidationException::withMessages([
                'course_section_id' => 'El curso seleccionado no pertenece al mismo año académico de la matrícula.',
            ]);
        }
    }

    private function resolveEffectiveDate(mixed $value, string $fallback): string
    {
        return DateInput::normalize($value) ?: $fallback;
    }
}
