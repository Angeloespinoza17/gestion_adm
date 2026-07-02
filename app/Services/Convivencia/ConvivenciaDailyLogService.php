<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaDailyLogService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
        private readonly ConvivenciaCaseService $caseService,
        private readonly ConvivenciaDerivationService $derivationService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaDailyLog
    {
        return DB::transaction(function () use ($payload, $user) {
            $dailyLog = new ConvivenciaDailyLog();
            $this->fillDailyLog($dailyLog, $payload, $user, true);
            $dailyLog->save();

            $this->supportService->logStatus($dailyLog, null, $dailyLog->status, $user, 'Bitácora registrada.', 'created');

            return $this->loadDailyLog($dailyLog);
        });
    }

    public function update(ConvivenciaDailyLog $dailyLog, array $payload, User $user): ConvivenciaDailyLog
    {
        return DB::transaction(function () use ($dailyLog, $payload, $user) {
            $previousStatus = $dailyLog->status;

            $this->fillDailyLog($dailyLog, $payload, $user, false);
            $dailyLog->save();

            if ($previousStatus !== $dailyLog->status) {
                $this->supportService->logStatus($dailyLog, $previousStatus, $dailyLog->status, $user);
            }

            return $this->loadDailyLog($dailyLog);
        });
    }

    public function convertToCase(ConvivenciaDailyLog $dailyLog, array $payload, User $user)
    {
        return $this->caseService->createFromDailyLog($dailyLog, $payload, $user);
    }

    public function convertToDerivation(ConvivenciaDailyLog $dailyLog, array $payload, User $user): ConvivenciaDerivation
    {
        return DB::transaction(function () use ($dailyLog, $payload, $user) {
            $derivation = $this->derivationService->store(array_merge([
                'case_id' => $dailyLog->case_id,
                'academic_year_id' => $dailyLog->academic_year_id,
                'course_section_id' => $dailyLog->course_section_id,
                'student_profile_id' => $dailyLog->student_profile_id,
                'responsible_user_id' => $payload['responsible_user_id'] ?? $user->id,
                'scope' => $payload['scope'] ?? 'internal',
                'status' => $payload['status'] ?? 'ingresada',
                'priority_level' => $payload['priority_level'] ?? 'media',
                'confidentiality_level' => $payload['confidentiality_level'] ?? 'reservada',
                'derived_at' => $payload['derived_at'] ?? now(),
                'motive' => $payload['motive'] ?? $dailyLog->daily_log_type_label ?? 'Bitácora diaria',
                'narrative' => $payload['narrative'] ?? $dailyLog->description,
                'is_sensitive' => $payload['is_sensitive'] ?? $dailyLog->is_sensitive,
            ], $payload), $user);

            $previousStatus = $dailyLog->status;
            $dailyLog->forceFill([
                'generated_derivation_id' => $derivation->id,
                'status' => 'convertido_derivacion',
                'updated_by' => $user->id,
            ])->save();

            $this->supportService->logStatus($dailyLog, $previousStatus, $dailyLog->status, $user, 'Bitácora convertida en derivación.');

            return $derivation;
        });
    }

    private function fillDailyLog(ConvivenciaDailyLog $dailyLog, array $payload, User $user, bool $creating): void
    {
        $type = !empty($payload['daily_log_type_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['daily_log_type_item_id'])
            : null;

        $dailyLog->fill([
            'case_id' => $payload['case_id'] ?? null,
            'generated_derivation_id' => $payload['generated_derivation_id'] ?? $dailyLog->generated_derivation_id,
            'academic_year_id' => $payload['academic_year_id'] ?? null,
            'course_section_id' => $payload['course_section_id'] ?? null,
            'student_profile_id' => $payload['student_profile_id'] ?? null,
            'daily_log_type_item_id' => $type?->id,
            'inspector_user_id' => $payload['inspector_user_id'] ?? $user->id,
            'inspector_staff_id' => $payload['inspector_staff_id'] ?? $user->staff_id,
            'happened_at' => $payload['happened_at'],
            'daily_log_type_label' => $payload['daily_log_type_label'] ?? $type?->name,
            'place' => $payload['place'] ?? null,
            'description' => $payload['description'],
            'immediate_action' => $payload['immediate_action'] ?? null,
            'involved_snapshot' => array_values($payload['involved_snapshot'] ?? []),
            'guardian_informed' => (bool) ($payload['guardian_informed'] ?? false),
            'guardian_contact_note' => $payload['guardian_contact_note'] ?? null,
            'status' => $payload['status'],
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? false),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $dailyLog->created_by = $user->id;
        }
    }

    private function loadDailyLog(ConvivenciaDailyLog $dailyLog): ConvivenciaDailyLog
    {
        return $dailyLog->fresh([
            'case:id,folio,status',
            'generatedDerivation:id,scope,status,destination_label',
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'student:id,first_name,last_name,registered_name,rut',
            'type:id,name',
            'inspectorUser:id,name',
            'inspectorStaff:id,full_name',
            'attachments.uploadedBy:id,name',
            'statusLogs.changedBy:id,name',
        ]);
    }
}
