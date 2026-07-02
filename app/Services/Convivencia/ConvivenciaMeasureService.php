<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaMeasureService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaMeasure
    {
        return DB::transaction(function () use ($payload, $user) {
            $measure = new ConvivenciaMeasure();
            $this->fillMeasure($measure, $payload, $user, true);
            $measure->save();

            $this->supportService->logStatus($measure, null, $measure->status, $user, 'Medida formativa creada.', 'created');

            return $this->loadMeasure($measure);
        });
    }

    public function update(ConvivenciaMeasure $measure, array $payload, User $user): ConvivenciaMeasure
    {
        return DB::transaction(function () use ($measure, $payload, $user) {
            $previousStatus = $measure->status;

            $this->fillMeasure($measure, $payload, $user, false);
            $measure->save();

            if ($previousStatus !== $measure->status) {
                $this->supportService->logStatus($measure, $previousStatus, $measure->status, $user);
            }

            return $this->loadMeasure($measure);
        });
    }

    private function fillMeasure(ConvivenciaMeasure $measure, array $payload, User $user, bool $creating): void
    {
        $type = !empty($payload['measure_type_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['measure_type_item_id'])
            : null;
        $status = $payload['status'];
        $isClosed = in_array($status, ['cumplida', 'incumplida', 'cerrada'], true);

        $measure->fill([
            'case_id' => $payload['case_id'] ?? null,
            'student_profile_id' => $payload['student_profile_id'] ?? null,
            'course_section_id' => $payload['course_section_id'] ?? null,
            'measure_type_item_id' => $type?->id,
            'responsible_user_id' => $payload['responsible_user_id'] ?? $user->id,
            'responsible_staff_id' => $payload['responsible_staff_id'] ?? $user->staff_id,
            'validated_by' => $payload['validated_by'] ?? ($isClosed ? ($measure->validated_by ?? $user->id) : $measure->validated_by),
            'measure_type_label' => $payload['measure_type_label'] ?? $type?->name,
            'description' => $payload['description'],
            'training_objective' => $payload['training_objective'],
            'assigned_at' => $payload['assigned_at'],
            'due_at' => $payload['due_at'] ?? null,
            'status' => $status,
            'evidence_summary' => $payload['evidence_summary'] ?? null,
            'student_reflection' => $payload['student_reflection'] ?? null,
            'repair_action' => $payload['repair_action'] ?? null,
            'responsible_notes' => $payload['responsible_notes'] ?? null,
            'closure_notes' => $payload['closure_notes'] ?? null,
            'closed_at' => $isClosed ? ($payload['closed_at'] ?? now()) : null,
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? true),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $measure->created_by = $user->id;
        }
    }

    private function loadMeasure(ConvivenciaMeasure $measure): ConvivenciaMeasure
    {
        return $measure->fresh([
            'case:id,folio,status,classification_label,criticality_label',
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'type:id,name',
            'responsibleUser:id,name',
            'responsibleStaff:id,full_name',
            'validator:id,name',
            'attachments.uploadedBy:id,name',
            'statusLogs.changedBy:id,name',
        ]);
    }
}
