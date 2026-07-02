<?php

namespace App\Services\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoConfigAttentionType;
use App\Models\ApoyoProfesional\ApoyoConfigMotivo;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApoyoProfesionalAttentionService
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalStudentContextService $studentContextService,
    ) {
    }

    public function store(array $payload, User $user): ApoyoAtencion
    {
        return DB::transaction(function () use ($payload, $user) {
            $attention = new ApoyoAtencion();
            $this->fillAttention($attention, $payload, $user, true);
            $attention->save();

            return $this->loadAttention($attention);
        });
    }

    public function update(ApoyoAtencion $attention, array $payload, User $user): ApoyoAtencion
    {
        return DB::transaction(function () use ($attention, $payload, $user) {
            $this->fillAttention($attention, $payload, $user, false);
            $attention->save();

            return $this->loadAttention($attention);
        });
    }

    public function close(ApoyoAtencion $attention, User $user, array $payload = []): ApoyoAtencion
    {
        $attention->forceFill([
            'status' => 'cerrada',
            'case_closed_at' => now(),
            'case_closed_by' => $user->id,
            'case_closed_notes' => $payload['case_closed_notes'] ?? $attention->case_closed_notes,
            'updated_by' => $user->id,
        ])->save();

        return $this->loadAttention($attention);
    }

    public function delete(ApoyoAtencion $attention): void
    {
        DB::transaction(function () use ($attention) {
            $attention->documents()->delete();
            $attention->delete();
        });
    }

    private function fillAttention(ApoyoAtencion $attention, array $payload, User $user, bool $creating): void
    {
        $student = StudentProfile::query()->findOrFail($payload['student_profile_id']);
        $attendedAt = Carbon::parse($payload['attended_at']);
        $summary = $this->studentContextService->studentSummary($student, $attendedAt);
        $currentEnrollment = $this->studentContextService->currentEnrollment($student);
        $teacher = !empty($payload['teacher_staff_id'])
            ? \App\Models\Staff::query()->find($payload['teacher_staff_id'])
            : $this->studentContextService->teacherForCourse($currentEnrollment?->courseSection);
        $professional = $this->accessService->professionalProfileForUser($user);
        $area = $this->accessService->professionalAreaForUser($user);
        $roleName = $this->accessService->professionalRoleNameForUser($user);
        $confidentiality = $payload['confidentiality_level'];
        $attentionType = !empty($payload['attention_type_id'])
            ? ApoyoConfigAttentionType::query()->find($payload['attention_type_id'])
            : null;
        $motive = !empty($payload['motive_id'])
            ? ApoyoConfigMotivo::query()->find($payload['motive_id'])
            : null;

        $attention->fill([
            'student_profile_id' => $student->id,
            'academic_year_id' => $payload['academic_year_id'] ?? $currentEnrollment?->academic_year_id,
            'course_section_id' => $payload['course_section_id'] ?? $currentEnrollment?->course_section_id,
            'teacher_staff_id' => $teacher?->id,
            'apoyo_profesional_id' => $payload['apoyo_profesional_id'] ?? $professional?->id,
            'attended_by_user_id' => $payload['attended_by_user_id'] ?? $user->id,
            'attention_type_id' => $payload['attention_type_id'] ?? null,
            'motive_id' => $payload['motive_id'] ?? null,
            'attended_at' => $attendedAt->format('Y-m-d H:i:s'),
            'professional_role_name' => $payload['professional_role_name'] ?? $roleName,
            'professional_area_slug' => $payload['professional_area_slug'] ?? $area['slug'],
            'professional_area_name' => $payload['professional_area_name'] ?? $area['name'],
            'student_full_name_snapshot' => $summary['full_name'],
            'student_rut_snapshot' => $summary['rut'],
            'course_name_snapshot' => $payload['course_name_snapshot'] ?? $summary['course'],
            'teacher_name_snapshot' => $teacher?->full_name ?: $summary['teacher_name'],
            'age_snapshot' => $summary['age'],
            'motive_label' => $payload['motive_label'] ?? $motive?->name,
            'attention_type_label' => $payload['attention_type_label'] ?? $attentionType?->name,
            'attention_type_other' => $payload['attention_type_other'] ?? null,
            'modality' => $payload['modality'],
            'modality_other' => $payload['modality_other'] ?? null,
            'origin' => $payload['origin'],
            'origin_other' => $payload['origin_other'] ?? null,
            'priority_level' => $payload['priority_level'],
            'confidentiality_level' => $confidentiality,
            'reason_summary' => $payload['reason_summary'],
            'description' => $payload['description'] ?? null,
            'professional_observations' => $payload['professional_observations'] ?? null,
            'agreements' => $payload['agreements'] ?? null,
            'recommendations' => $payload['recommendations'] ?? null,
            'next_action' => $payload['next_action'] ?? null,
            'status' => $payload['status'],
            'case_closed_notes' => $payload['case_closed_notes'] ?? $attention->case_closed_notes,
            'is_confidential_case' => in_array($confidentiality, ['confidencial', 'alta_confidencialidad'], true),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $attention->created_by = $user->id;
        }
    }

    private function loadAttention(ApoyoAtencion $attention): ApoyoAtencion
    {
        return $attention->fresh([
            'student:id,first_name,last_name,registered_name,rut,birthdate,guardian_name,guardian_phone,guardian_email',
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'teacher:id,full_name',
            'professional:id,user_id,staff_id,area_slug,area_name,professional_role_name,can_manage_confidential_cases',
            'professional.staff:id,full_name',
            'attendedBy:id,name,email,staff_id',
            'attentionType:id,slug,name,requires_other_description',
            'motive:id,slug,name,area_slug',
            'derivations.destinationProfessional.staff:id,full_name',
            'derivations.destinationUser:id,name',
            'followUps.responsibleProfessional.staff:id,full_name',
            'followUps.responsibleUser:id,name',
            'documents.uploadedBy:id,name',
            'closedBy:id,name',
        ]);
    }
}
