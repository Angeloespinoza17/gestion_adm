<?php

namespace App\Services\Security;

use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityIncidentAssignment;
use App\Models\Security\SecurityIncidentStatus;
use App\Models\Security\SecurityRound;
use App\Models\Security\SecurityRoundSector;
use App\Models\Security\SecurityShift;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecurityRoundService
{
    public function __construct(
        private readonly SecurityIncidentAlertService $alertService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, UploadedFile>  $files
     */
    public function createRound(SecurityShift $shift, array $payload, array $files, User $user): SecurityRound
    {
        $this->validatePayload($payload);

        return DB::transaction(function () use ($shift, $payload, $files, $user) {
            $shift->refresh();

            $roundNumber = ((int) $shift->rounds()->max('round_number')) + 1;
            $recordedAt = Carbon::parse((string) Arr::get($payload, 'recorded_at', now()->toDateTimeString()));
            $sectorsPayload = collect(Arr::get($payload, 'sectors', []));
            $incidentsPayload = collect(Arr::get($payload, 'incidents', []));

            $overallStatus = Arr::get($payload, 'overall_status');
            if (!$overallStatus) {
                $overallStatus = $incidentsPayload->isNotEmpty()
                    ? SecurityRound::STATUS_REQUIERE_ATENCION
                    : ($sectorsPayload->contains(fn ($item) => ($item['sector_state'] ?? 'sin_novedad') !== 'sin_novedad')
                        ? SecurityRound::STATUS_OBSERVADO
                        : SecurityRound::STATUS_SIN_NOVEDAD);
            }

            $round = SecurityRound::create([
                'security_shift_id' => $shift->id,
                'recorded_by_user_id' => $user->id,
                'round_number' => $roundNumber,
                'recorded_at' => $recordedAt,
                'overall_status' => $overallStatus,
                'observations' => Arr::get($payload, 'observations'),
                'nochero_confirmation_name' => Arr::get($payload, 'nochero_confirmation_name', $shift->staff?->full_name ?: $user->name),
                'signature_data' => Arr::get($payload, 'signature_data'),
                'latitude' => Arr::get($payload, 'latitude'),
                'longitude' => Arr::get($payload, 'longitude'),
                'location_accuracy' => Arr::get($payload, 'location_accuracy'),
                'act_number' => $this->actNumber($shift, $roundNumber, $recordedAt),
                'act_generated_at' => now(),
            ]);

            $sectorMap = [];
            foreach ($sectorsPayload->values() as $index => $sectorData) {
                $sector = SecurityRoundSector::create([
                    'security_round_id' => $round->id,
                    'maintenance_dependency_id' => Arr::get($sectorData, 'maintenance_dependency_id'),
                    'sector_name' => (string) Arr::get($sectorData, 'sector_name', 'Sector sin nombre'),
                    'sector_state' => (string) Arr::get($sectorData, 'sector_state', 'sin_novedad'),
                    'observations' => Arr::get($sectorData, 'observations'),
                    'display_order' => $index + 1,
                ]);

                if (!empty($sectorData['temp_key'])) {
                    $sectorMap[(string) $sectorData['temp_key']] = $sector;
                }
            }

            foreach ((array) Arr::get($payload, 'round_evidence_keys', []) as $key) {
                $this->storeEvidence($round, $files[$key] ?? null, $user, 'foto', 'Evidencia de ronda');
            }

            $statusId = SecurityIncidentStatus::query()
                ->where('code', SecurityIncidentStatus::defaultCode())
                ->value('id');

            foreach ($incidentsPayload->values() as $incidentData) {
                $sector = isset($incidentData['sector_temp_key']) ? ($sectorMap[(string) $incidentData['sector_temp_key']] ?? null) : null;
                $dependencyId = Arr::get($incidentData, 'maintenance_dependency_id') ?: $sector?->maintenance_dependency_id;
                $assigneeIds = $this->resolveAssigneeUserIds($incidentData);

                $incident = SecurityIncident::create([
                    'security_shift_id' => $shift->id,
                    'security_round_id' => $round->id,
                    'security_round_sector_id' => $sector?->id,
                    'reported_by_user_id' => $user->id,
                    'status_id' => $statusId,
                    'maintenance_dependency_id' => $dependencyId,
                    'inventory_item_id' => Arr::get($incidentData, 'inventory_item_id'),
                    'current_responsible_user_id' => $assigneeIds[0] ?? null,
                    'priority' => Arr::get($incidentData, 'priority', SecurityIncident::PRIORITY_BAJA),
                    'title' => (string) Arr::get($incidentData, 'title'),
                    'description' => (string) Arr::get($incidentData, 'description'),
                    'sector_name' => (string) ($sector?->sector_name ?: Arr::get($incidentData, 'sector_name', 'Todo el colegio')),
                    'requires_immediate_attention' => in_array(Arr::get($incidentData, 'priority'), [SecurityIncident::PRIORITY_ALTA, SecurityIncident::PRIORITY_CRITICA], true),
                    'response_due_at' => $this->responseDueAt((string) Arr::get($incidentData, 'priority', SecurityIncident::PRIORITY_BAJA), $recordedAt),
                ]);

                $this->syncAssignments($incident, $assigneeIds, $user);

                foreach ((array) Arr::get($incidentData, 'evidence_keys', []) as $key) {
                    $this->storeEvidence($incident, $files[$key] ?? null, $user, 'foto', $incident->title);
                }

                $this->alertService->dispatchIfNeeded($incident->fresh([
                    'reportedBy:id,name,email',
                    'shift.staff:id,full_name',
                    'assignments.user:id,name,email,active',
                    'currentResponsible:id,name,email,active',
                ]));
            }

            if ($shift->status === SecurityShift::STATUS_PROGRAMADO) {
                $shift->update([
                    'status' => SecurityShift::STATUS_EN_CURSO,
                    'started_at' => $shift->started_at ?: now(),
                    'started_by_user_id' => $shift->started_by_user_id ?: $user->id,
                ]);
            }

            return $round->load([
                'recordedBy:id,name,email',
                'sectors.dependency:id,code,name,sector,zone',
                'incidents.status:id,code,name,color',
                'incidents.currentResponsible:id,name,email',
                'incidents.evidences',
                'evidences',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(array $payload): void
    {
        $sectors = Arr::get($payload, 'sectors', []);

        if (!is_array($sectors) || empty($sectors)) {
            throw ValidationException::withMessages([
                'payload' => 'Debes registrar al menos un sector revisado en la ronda.',
            ]);
        }

        foreach ((array) Arr::get($payload, 'incidents', []) as $index => $incident) {
            if (empty($incident['title']) || empty($incident['description'])) {
                throw ValidationException::withMessages([
                    "payload.incidents.{$index}" => 'Cada novedad debe incluir título y descripción.',
                ]);
            }
        }
    }

    private function actNumber(SecurityShift $shift, int $roundNumber, Carbon $recordedAt): string
    {
        return sprintf(
            'ACTA-RS-%s-%04d-%02d',
            $recordedAt->format('Ymd'),
            $shift->id,
            $roundNumber
        );
    }

    private function responseDueAt(string $priority, Carbon $baseDate): ?Carbon
    {
        return match ($priority) {
            SecurityIncident::PRIORITY_CRITICA => $baseDate->copy()->addHour(),
            SecurityIncident::PRIORITY_ALTA => $baseDate->copy()->addHours(4),
            SecurityIncident::PRIORITY_MEDIA => $baseDate->copy()->addDay(),
            default => null,
        };
    }

    /**
     * @return array<int, int>
     */
    private function resolveAssigneeUserIds(array $incidentData): array
    {
        return collect((array) Arr::get($incidentData, 'assignee_user_ids', []))
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function syncAssignments(SecurityIncident $incident, array $userIds, User $actor): void
    {
        $incident->assignments()->where('is_current', true)->update([
            'is_current' => false,
            'released_at' => now(),
        ]);

        $uniqueIds = collect($userIds)->filter()->unique()->values();

        foreach ($uniqueIds as $index => $userId) {
            SecurityIncidentAssignment::create([
                'security_incident_id' => $incident->id,
                'user_id' => (int) $userId,
                'assigned_by_user_id' => $actor->id,
                'assigned_at' => now(),
                'is_current' => true,
                'notes' => $index === 0 ? 'Responsable principal' : 'Responsable adicional',
            ]);
        }

        $incident->update([
            'current_responsible_user_id' => $uniqueIds->first(),
        ]);
    }

    private function storeEvidence($attachable, ?UploadedFile $file, User $user, string $kind, ?string $caption = null): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }

        $folder = sprintf('security/%s/%d', class_basename($attachable), $attachable->id);
        $path = $file->store($folder, 'public');

        $attachable->evidences()->create([
            'uploaded_by_user_id' => $user->id,
            'kind' => $kind,
            'file_path' => $path,
            'caption' => $caption,
            'taken_at' => now(),
        ]);
    }
}
