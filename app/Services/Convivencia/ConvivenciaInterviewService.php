<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaInterviewService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaInterview
    {
        return DB::transaction(function () use ($payload, $user) {
            $interview = new ConvivenciaInterview();
            $this->fillInterview($interview, $payload, $user, true);
            $interview->save();

            $this->supportService->syncInterviewParticipants($interview, $payload['participants'] ?? []);
            $this->supportService->logStatus($interview, null, $interview->follow_up_status, $user, 'Entrevista registrada.', 'created');

            return $this->loadInterview($interview);
        });
    }

    public function update(ConvivenciaInterview $interview, array $payload, User $user): ConvivenciaInterview
    {
        return DB::transaction(function () use ($interview, $payload, $user) {
            $previousStatus = $interview->follow_up_status;

            $this->fillInterview($interview, $payload, $user, false);
            $interview->save();

            $this->supportService->syncInterviewParticipants($interview, $payload['participants'] ?? []);

            if ($previousStatus !== $interview->follow_up_status) {
                $this->supportService->logStatus($interview, $previousStatus, $interview->follow_up_status, $user);
            }

            return $this->loadInterview($interview);
        });
    }

    private function fillInterview(ConvivenciaInterview $interview, array $payload, User $user, bool $creating): void
    {
        $type = !empty($payload['interview_type_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['interview_type_item_id'])
            : null;

        $interview->fill([
            'case_id' => $payload['case_id'] ?? null,
            'student_profile_id' => $payload['student_profile_id'] ?? null,
            'course_section_id' => $payload['course_section_id'] ?? null,
            'interview_type_item_id' => $type?->id,
            'responsible_user_id' => $payload['responsible_user_id'] ?? $user->id,
            'responsible_staff_id' => $payload['responsible_staff_id'] ?? $user->staff_id,
            'interview_type_label' => $payload['interview_type_label'] ?? $type?->name,
            'interview_at' => $payload['interview_at'],
            'motive' => $payload['motive'],
            'topics' => $payload['topics'] ?? null,
            'agreements' => $payload['agreements'] ?? null,
            'commitments' => $payload['commitments'] ?? null,
            'follow_up_date' => $payload['follow_up_date'] ?? null,
            'follow_up_status' => $payload['follow_up_status'],
            'internal_notes' => $payload['internal_notes'] ?? null,
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? true),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $interview->created_by = $user->id;
        }
    }

    private function loadInterview(ConvivenciaInterview $interview): ConvivenciaInterview
    {
        return $interview->fresh([
            'case:id,folio,status',
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'type:id,name',
            'responsibleUser:id,name',
            'responsibleStaff:id,full_name',
            'participants.student:id,first_name,last_name,registered_name,rut',
            'participants.user:id,name',
            'participants.staff:id,full_name',
            'attachments.uploadedBy:id,name',
            'statusLogs.changedBy:id,name',
        ]);
    }
}
