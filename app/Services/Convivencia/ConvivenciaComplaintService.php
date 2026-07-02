<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaComplaintService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
        private readonly ConvivenciaCaseService $caseService,
    ) {
    }

    public function store(array $payload, ?User $user = null): ConvivenciaComplaint
    {
        return DB::transaction(function () use ($payload, $user) {
            $complaint = new ConvivenciaComplaint();
            $this->fillComplaint($complaint, $payload, $user, true);
            $complaint->save();

            $this->supportService->logStatus($complaint, null, $complaint->status, $user, 'Denuncia ingresada.', 'created');

            return $this->loadComplaint($complaint);
        });
    }

    public function update(ConvivenciaComplaint $complaint, array $payload, User $user): ConvivenciaComplaint
    {
        return DB::transaction(function () use ($complaint, $payload, $user) {
            $previousStatus = $complaint->status;

            $this->fillComplaint($complaint, $payload, $user, false);
            $complaint->save();

            if ($previousStatus !== $complaint->status) {
                $this->supportService->logStatus($complaint, $previousStatus, $complaint->status, $user);
            }

            return $this->loadComplaint($complaint);
        });
    }

    public function convertToCase(ConvivenciaComplaint $complaint, array $payload, User $user): ConvivenciaCase
    {
        return $this->caseService->createFromComplaint($complaint, $payload, $user);
    }

    private function fillComplaint(ConvivenciaComplaint $complaint, array $payload, ?User $user, bool $creating): void
    {
        $situationType = !empty($payload['situation_type_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['situation_type_item_id'])
            : null;

        $complaint->fill([
            'academic_year_id' => $payload['academic_year_id'] ?? null,
            'course_section_id' => $payload['course_section_id'] ?? null,
            'affected_student_id' => $payload['affected_student_id'] ?? null,
            'situation_type_item_id' => $situationType?->id,
            'responsible_user_id' => $payload['responsible_user_id'] ?? $complaint->responsible_user_id ?? $user?->id,
            'case_id' => $payload['case_id'] ?? $complaint->case_id,
            'complainant_name' => $payload['complainant_name'] ?? null,
            'complainant_type' => $payload['complainant_type'],
            'contact_email' => $payload['contact_email'] ?? null,
            'contact_phone' => $payload['contact_phone'] ?? null,
            'situation_type_label' => $payload['situation_type_label'] ?? $situationType?->name,
            'place' => $payload['place'] ?? null,
            'received_at' => $payload['received_at'] ?? now(),
            'happened_at' => $payload['happened_at'] ?? null,
            'report_text' => $payload['report_text'],
            'involved_snapshot' => array_values($payload['involved_snapshot'] ?? []),
            'truth_declaration_accepted' => (bool) ($payload['truth_declaration_accepted'] ?? false),
            'is_anonymous' => ($payload['complainant_type'] ?? null) === 'anonimo' || (bool) ($payload['is_anonymous'] ?? false),
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? true),
            'status' => $payload['status'],
            'admissibility_result' => $payload['admissibility_result'] ?? null,
            'updated_by' => $user?->id,
        ]);

        if ($creating) {
            $complaint->folio = $this->supportService->nextFolio('DEN', ConvivenciaComplaint::query());
            $complaint->created_by = $user?->id;
        }
    }

    private function loadComplaint(ConvivenciaComplaint $complaint): ConvivenciaComplaint
    {
        return $complaint->fresh([
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'affectedStudent:id,first_name,last_name,registered_name,rut',
            'situationType:id,name',
            'responsibleUser:id,name',
            'case:id,folio,status',
            'protocolActivations.protocol:id,name',
            'attachments.uploadedBy:id,name',
            'statusLogs.changedBy:id,name',
        ]);
    }
}
