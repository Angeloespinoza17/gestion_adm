<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaDerivationService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaDerivation
    {
        return DB::transaction(function () use ($payload, $user) {
            $derivation = new ConvivenciaDerivation();
            $this->fillDerivation($derivation, $payload, $user, true);
            $derivation->save();

            $this->supportService->logStatus($derivation, null, $derivation->status, $user, 'Derivación creada.', 'created');
            $this->syncCaseStatus($derivation, $user);

            return $this->loadDerivation($derivation);
        });
    }

    public function update(ConvivenciaDerivation $derivation, array $payload, User $user): ConvivenciaDerivation
    {
        return DB::transaction(function () use ($derivation, $payload, $user) {
            $previousStatus = $derivation->status;

            $this->fillDerivation($derivation, $payload, $user, false);
            $derivation->save();

            if ($previousStatus !== $derivation->status) {
                $this->supportService->logStatus($derivation, $previousStatus, $derivation->status, $user);
            }

            $this->syncCaseStatus($derivation, $user);

            return $this->loadDerivation($derivation);
        });
    }

    private function fillDerivation(ConvivenciaDerivation $derivation, array $payload, User $user, bool $creating): void
    {
        $derivation->fill([
            'case_id' => $payload['case_id'] ?? null,
            'academic_year_id' => $payload['academic_year_id'] ?? null,
            'course_section_id' => $payload['course_section_id'] ?? null,
            'student_profile_id' => $payload['student_profile_id'] ?? null,
            'destination_department_id' => $payload['destination_department_id'] ?? null,
            'destination_staff_id' => $payload['destination_staff_id'] ?? null,
            'destination_user_id' => $payload['destination_user_id'] ?? null,
            'external_institution_id' => $payload['external_institution_id'] ?? null,
            'responsible_user_id' => $payload['responsible_user_id'] ?? $user->id,
            'scope' => $payload['scope'],
            'status' => $payload['status'],
            'priority_level' => $payload['priority_level'],
            'confidentiality_level' => $payload['confidentiality_level'],
            'destination_label' => $payload['destination_label'] ?? null,
            'external_contact_name' => $payload['external_contact_name'] ?? null,
            'external_contact_email' => $payload['external_contact_email'] ?? null,
            'external_contact_phone' => $payload['external_contact_phone'] ?? null,
            'derived_at' => $payload['derived_at'],
            'sent_at' => $payload['sent_at'] ?? null,
            'response_due_at' => $payload['response_due_at'] ?? null,
            'responded_at' => $payload['responded_at'] ?? null,
            'closed_at' => in_array($payload['status'], ['cerrada', 'rechazada'], true) ? ($payload['closed_at'] ?? now()) : null,
            'motive' => $payload['motive'],
            'narrative' => $payload['narrative'] ?? null,
            'response_text' => $payload['response_text'] ?? null,
            'suggested_actions' => $payload['suggested_actions'] ?? null,
            'follow_up_notes' => $payload['follow_up_notes'] ?? null,
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? true),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $derivation->created_by = $user->id;
        }
    }

    private function syncCaseStatus(ConvivenciaDerivation $derivation, User $user): void
    {
        $case = $derivation->case;

        if (!$case || in_array($case->status, ['cerrado', 'archivado'], true)) {
            return;
        }

        $previousStatus = $case->status;
        $case->forceFill([
            'status' => 'derivado',
            'updated_by' => $user->id,
        ])->save();

        if ($previousStatus !== $case->status) {
            $this->supportService->logStatus($case, $previousStatus, $case->status, $user, 'Caso marcado como derivado.');
        }
    }

    private function loadDerivation(ConvivenciaDerivation $derivation): ConvivenciaDerivation
    {
        return $derivation->fresh([
            'case:id,folio,status,classification_label,criticality_label',
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'student:id,first_name,last_name,registered_name,rut',
            'destinationDepartment:id,name',
            'destinationStaff:id,full_name',
            'destinationUser:id,name',
            'externalInstitution:id,name,category',
            'responsibleUser:id,name',
            'attachments.uploadedBy:id,name',
            'statusLogs.changedBy:id,name',
        ]);
    }
}
