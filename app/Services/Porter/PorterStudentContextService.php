<?php

namespace App\Services\Porter;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Support\Rut;

class PorterStudentContextService
{
    public function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    public function currentEnrollment(StudentProfile $student, ?AcademicYear $academicYear = null): ?StudentEnrollment
    {
        $year = $academicYear ?: $this->activeAcademicYear();

        if (!$year) {
            return null;
        }

        $student->loadMissing([
            'enrollments.academicYear:id,name,year,is_active',
            'enrollments.courseSection:id,academic_year_id,education_level_id,section_name,display_name',
            'enrollments.courseSection.educationLevel:id,name,order,type',
        ]);

        return $student->preferredEnrollment($year);
    }

    public function authorizedPickupPeople(StudentProfile $student): array
    {
        $people = collect([
            $this->normalizePerson([
                'name' => $student->guardian_name,
                'rut' => $student->guardian_rut,
                'relationship' => $student->guardian_relationship ?: $student->guardian_role ?: 'Apoderado titular',
                'phone' => $student->guardian_phone,
                'source' => 'apoderado_titular',
            ]),
            $this->normalizePerson([
                'name' => $student->guardian_backup_name,
                'rut' => $student->guardian_backup_rut,
                'relationship' => $student->guardian_backup_relationship ?: $student->guardian_backup_role ?: 'Apoderado suplente',
                'phone' => $student->guardian_backup_phone,
                'source' => 'apoderado_suplente',
            ]),
        ]);

        $extras = collect($student->authorized_pickup_people ?: [])
            ->map(fn ($item) => $this->normalizePerson([
                'name' => $item['name'] ?? null,
                'rut' => $item['rut'] ?? null,
                'relationship' => $item['relationship'] ?? null,
                'phone' => $item['phone'] ?? null,
                'source' => 'lista_porteria',
            ]));

        return $people
            ->merge($extras)
            ->filter(fn ($item) => !empty($item['name']))
            ->unique(fn ($item) => strtolower(($item['name'] ?? '') . '|' . ($item['rut'] ?? '')))
            ->values()
            ->all();
    }

    public function medicalAlerts(StudentProfile $student): array
    {
        $alerts = [];

        if ($student->has_chronic_illness && $student->chronic_illness_details) {
            $alerts[] = 'Enfermedad crónica: ' . $student->chronic_illness_details;
        }

        if ($student->has_medication_allergies && $student->medication_allergies_details) {
            $alerts[] = 'Alergias: ' . $student->medication_allergies_details;
        }

        if ($student->has_physical_restrictions && $student->physical_restrictions_details) {
            $alerts[] = 'Restricciones físicas: ' . $student->physical_restrictions_details;
        }

        return $alerts;
    }

    public function porterAlerts(StudentProfile $student, ?StudentEnrollment $enrollment): array
    {
        $alerts = collect();

        if ($student->pickup_restriction) {
            $alerts->push([
                'type' => 'pickup_restriction',
                'label' => 'Restricción de retiro',
                'detail' => $student->pickup_restriction_notes ?: 'La estudiante requiere validación especial para retiros.',
                'priority' => 'high',
            ]);
        }

        foreach ($this->medicalAlerts($student) as $detail) {
            $alerts->push([
                'type' => 'medical',
                'label' => 'Alerta médica',
                'detail' => $detail,
                'priority' => 'medium',
            ]);
        }

        if ($student->porter_alert_notes) {
            $alerts->push([
                'type' => 'porter_notes',
                'label' => 'Observación de portería',
                'detail' => $student->porter_alert_notes,
                'priority' => 'medium',
            ]);
        }

        if ($enrollment && in_array($enrollment->enrollment_status, ['retirada', 'trasladada', 'egresada'], true)) {
            $alerts->push([
                'type' => 'inactive_enrollment',
                'label' => 'Matrícula no activa',
                'detail' => 'El estado actual de matrícula es ' . $enrollment->enrollment_status . '.',
                'priority' => 'high',
            ]);
        }

        if ($student->general_status !== 'activo') {
            $alerts->push([
                'type' => 'general_status',
                'label' => 'Estado general',
                'detail' => 'La estudiante figura como ' . $student->general_status . '.',
                'priority' => 'medium',
            ]);
        }

        return $alerts->values()->all();
    }

    public function resolveAuthorizedPerson(StudentProfile $student, array $person): array
    {
        $normalizedRut = Rut::normalize((string) ($person['rut'] ?? null));
        $normalizedName = mb_strtolower(trim((string) ($person['name'] ?? '')));

        $match = collect($this->authorizedPickupPeople($student))->first(function ($allowed) use ($normalizedRut, $normalizedName) {
            $allowedRut = Rut::normalize((string) ($allowed['rut'] ?? null));
            $allowedName = mb_strtolower(trim((string) ($allowed['name'] ?? '')));

            if ($normalizedRut && $allowedRut && $normalizedRut === $allowedRut) {
                return true;
            }

            return $normalizedName !== '' && $allowedName !== '' && $normalizedName === $allowedName;
        });

        return [
            'authorized' => (bool) $match,
            'source' => $match['source'] ?? null,
            'matched_person' => $match,
        ];
    }

    public function porterStudentPayload(StudentProfile $student, ?StudentEnrollment $enrollment = null): array
    {
        $currentEnrollment = $enrollment ?: $this->currentEnrollment($student);

        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'registered_name' => $student->registered_name_resolved,
            'rut' => $student->rut,
            'birthdate' => $student->birthdate?->format('Y-m-d'),
            'email' => $student->email,
            'phone' => $student->phone,
            'address' => $student->address,
            'general_status' => $student->general_status,
            'photo_url' => null,
            'guardian_name' => $student->guardian_name,
            'guardian_rut' => $student->guardian_rut,
            'guardian_phone' => $student->guardian_phone,
            'guardian_email' => $student->guardian_email,
            'guardian_address' => $student->guardian_address,
            'guardian_relationship' => $student->guardian_relationship ?: $student->guardian_role,
            'guardian_backup_name' => $student->guardian_backup_name,
            'guardian_backup_rut' => $student->guardian_backup_rut,
            'guardian_backup_phone' => $student->guardian_backup_phone,
            'guardian_backup_email' => $student->guardian_backup_email,
            'guardian_backup_address' => $student->guardian_backup_address,
            'guardian_backup_relationship' => $student->guardian_backup_relationship ?: $student->guardian_backup_role,
            'father_name' => $student->father_name,
            'father_phone' => $student->father_phone,
            'father_email' => $student->father_email,
            'mother_name' => $student->mother_name,
            'mother_phone' => $student->mother_phone,
            'mother_email' => $student->mother_email,
            'current_enrollment' => $currentEnrollment ? [
                'academic_year_id' => $currentEnrollment->academic_year_id,
                'academic_year_name' => $currentEnrollment->snapshot_year_name,
                'course_section_id' => $currentEnrollment->course_section_id,
                'course_name' => $currentEnrollment->snapshot_course_display_name,
                'education_level_name' => $currentEnrollment->courseSection?->educationLevel?->name ?? $currentEnrollment->snapshot_level_name,
                'section_name' => $currentEnrollment->snapshot_section_name,
                'enrollment_status' => $currentEnrollment->enrollment_status,
            ] : null,
            'authorized_pickup_people' => $this->authorizedPickupPeople($student),
            'health_insurance' => $student->health_insurance,
            'has_chronic_illness' => (bool) $student->has_chronic_illness,
            'chronic_illness_details' => $student->chronic_illness_details,
            'has_medication_allergies' => (bool) $student->has_medication_allergies,
            'medication_allergies_details' => $student->medication_allergies_details,
            'has_physical_restrictions' => (bool) $student->has_physical_restrictions,
            'physical_restrictions_details' => $student->physical_restrictions_details,
            'observations' => $student->observations,
            'porter_alert_notes' => $student->porter_alert_notes,
            'pickup_restriction' => (bool) $student->pickup_restriction,
            'pickup_restriction_notes' => $student->pickup_restriction_notes,
            'alerts' => $this->porterAlerts($student, $currentEnrollment),
        ];
    }

    private function normalizePerson(array $person): ?array
    {
        $name = trim((string) ($person['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'rut' => Rut::normalize((string) ($person['rut'] ?? null)),
            'relationship' => trim((string) ($person['relationship'] ?? '')) ?: null,
            'phone' => trim((string) ($person['phone'] ?? '')) ?: null,
            'source' => $person['source'] ?? null,
        ];
    }
}
