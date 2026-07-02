<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaProtocolService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaProtocol
    {
        return DB::transaction(function () use ($payload, $user) {
            $protocol = new ConvivenciaProtocol();
            $this->fillProtocol($protocol, $payload, $user, true);
            $protocol->save();

            $this->supportService->syncProtocolSteps($protocol, $payload['steps'] ?? []);
            $this->supportService->logStatus($protocol, null, $protocol->status, $user, 'Protocolo creado.', 'created');

            return $this->loadProtocol($protocol);
        });
    }

    public function update(ConvivenciaProtocol $protocol, array $payload, User $user): ConvivenciaProtocol
    {
        return DB::transaction(function () use ($protocol, $payload, $user) {
            $previousStatus = $protocol->status;

            $this->fillProtocol($protocol, $payload, $user, false);
            $protocol->save();

            $this->supportService->syncProtocolSteps($protocol, $payload['steps'] ?? []);

            if ($previousStatus !== $protocol->status) {
                $this->supportService->logStatus($protocol, $previousStatus, $protocol->status, $user);
            }

            return $this->loadProtocol($protocol);
        });
    }

    public function activate(array $payload, User $user): ConvivenciaProtocolActivation
    {
        return DB::transaction(function () use ($payload, $user) {
            $protocol = ConvivenciaProtocol::query()->findOrFail($payload['protocol_id']);
            $currentStep = !empty($payload['current_step_id'])
                ? $protocol->steps()->find($payload['current_step_id'])
                : $protocol->steps()->orderBy('step_order')->first();

            $dueAt = $payload['due_at']
                ?? (($currentStep?->due_days ?? $protocol->default_due_days)
                    ? now()->addDays((int) ($currentStep?->due_days ?? $protocol->default_due_days))
                    : null);

            $activation = ConvivenciaProtocolActivation::query()->create([
                'protocol_id' => $protocol->id,
                'case_id' => $payload['case_id'] ?? null,
                'complaint_id' => $payload['complaint_id'] ?? null,
                'current_step_id' => $currentStep?->id,
                'activated_by' => $user->id,
                'activated_at' => now(),
                'status' => $payload['status'] ?? 'activo',
                'current_stage_name' => $payload['current_stage_name'] ?? $currentStep?->stage_name,
                'due_at' => $dueAt,
                'involved_snapshot' => $payload['involved_snapshot'] ?? null,
                'actions_taken' => $payload['actions_taken'] ?? null,
                'measures_adopted' => $payload['measures_adopted'] ?? null,
                'closing_summary' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $activation->logs()->create([
                'protocol_step_id' => $currentStep?->id,
                'created_by' => $user->id,
                'action_type' => 'activacion',
                'stage_name' => $activation->current_stage_name,
                'notes' => 'Protocolo activado.',
                'due_at' => $activation->due_at,
            ]);

            $this->supportService->logStatus($activation, null, $activation->status, $user, 'Protocolo activado.', 'created');
            $this->syncRelatedStatuses($activation, $user);

            return $this->loadActivation($activation);
        });
    }

    public function updateActivation(ConvivenciaProtocolActivation $activation, array $payload, User $user): ConvivenciaProtocolActivation
    {
        return DB::transaction(function () use ($activation, $payload, $user) {
            $previousStatus = $activation->status;

            $activation->forceFill([
                'current_step_id' => $payload['current_step_id'] ?? $activation->current_step_id,
                'status' => $payload['status'] ?? $activation->status,
                'current_stage_name' => $payload['current_stage_name'] ?? $activation->current_stage_name,
                'due_at' => $payload['due_at'] ?? $activation->due_at,
                'actions_taken' => $payload['actions_taken'] ?? $activation->actions_taken,
                'measures_adopted' => $payload['measures_adopted'] ?? $activation->measures_adopted,
                'closing_summary' => $payload['closing_summary'] ?? $activation->closing_summary,
                'closed_at' => in_array($payload['status'] ?? $activation->status, ['cerrado'], true) ? now() : $activation->closed_at,
                'updated_by' => $user->id,
            ])->save();

            $activation->logs()->create([
                'protocol_step_id' => $activation->current_step_id,
                'created_by' => $user->id,
                'action_type' => $payload['action_type'] ?? 'avance',
                'stage_name' => $activation->current_stage_name,
                'notes' => $payload['log_notes'] ?? null,
                'due_at' => $activation->due_at,
                'completed_at' => $payload['completed_at'] ?? null,
            ]);

            if ($previousStatus !== $activation->status) {
                $this->supportService->logStatus($activation, $previousStatus, $activation->status, $user);
            }

            $this->syncRelatedStatuses($activation, $user);

            return $this->loadActivation($activation);
        });
    }

    private function fillProtocol(ConvivenciaProtocol $protocol, array $payload, User $user, bool $creating): void
    {
        $type = !empty($payload['protocol_type_item_id'])
            ? \App\Models\Convivencia\ConvivenciaCatalogItem::query()->find($payload['protocol_type_item_id'])
            : null;
        $criticality = !empty($payload['criticality_item_id'])
            ? \App\Models\Convivencia\ConvivenciaCatalogItem::query()->find($payload['criticality_item_id'])
            : null;

        $protocol->fill([
            'protocol_type_item_id' => $type?->id,
            'criticality_item_id' => $criticality?->id,
            'name' => $payload['name'],
            'type_label' => $payload['type_label'] ?? $type?->name,
            'criticality_label' => $payload['criticality_label'] ?? $criticality?->name,
            'description' => $payload['description'] ?? null,
            'required_documents' => $payload['required_documents'] ?? null,
            'safeguard_measures' => $payload['safeguard_measures'] ?? null,
            'minimal_actions' => $payload['minimal_actions'] ?? null,
            'default_due_days' => $payload['default_due_days'] ?? null,
            'status' => $payload['status'],
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? true),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $protocol->created_by = $user->id;
        }
    }

    private function syncRelatedStatuses(ConvivenciaProtocolActivation $activation, User $user): void
    {
        if ($activation->case) {
            $case = $activation->case;
            $previousStatus = $case->status;
            $nextStatus = $activation->status === 'cerrado' ? 'en_seguimiento' : 'con_protocolo_activo';
            $case->forceFill([
                'status' => $nextStatus,
                'updated_by' => $user->id,
            ])->save();

            if ($previousStatus !== $nextStatus) {
                $this->supportService->logStatus($case, $previousStatus, $nextStatus, $user, 'Estado actualizado por activación de protocolo.');
            }
        }

        if ($activation->complaint) {
            $complaint = $activation->complaint;
            $previousStatus = $complaint->status;
            $nextStatus = $activation->status === 'cerrado' ? 'cerrada' : 'protocolo_activado';
            $complaint->forceFill([
                'status' => $nextStatus,
                'updated_by' => $user->id,
            ])->save();

            if ($previousStatus !== $nextStatus) {
                $this->supportService->logStatus($complaint, $previousStatus, $nextStatus, $user, 'Estado actualizado por activación de protocolo.');
            }
        }
    }

    private function loadProtocol(ConvivenciaProtocol $protocol): ConvivenciaProtocol
    {
        return $protocol->fresh([
            'type:id,name',
            'criticality:id,name,color',
            'steps',
            'activations',
        ]);
    }

    private function loadActivation(ConvivenciaProtocolActivation $activation): ConvivenciaProtocolActivation
    {
        return $activation->fresh([
            'protocol:id,name',
            'case:id,folio,status',
            'complaint:id,folio,status',
            'currentStep:id,stage_name,step_order',
            'activatedBy:id,name',
            'logs.createdBy:id,name',
            'logs.protocolStep:id,stage_name',
        ]);
    }
}
