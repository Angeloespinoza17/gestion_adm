<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\Convivencia\ConvivenciaProtocolActivationPart;
use App\Models\Convivencia\ConvivenciaProtocolActivationStep;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\Convivencia\ConvivenciaProtocolPartLink;
use App\Models\Convivencia\ConvivenciaProtocolStep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConvivenciaProtocolService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
        private readonly ConvivenciaAccessService $accessService,
        private readonly ConvivenciaProtocolDeadlineService $deadlineService,
    ) {}

    public function store(array $payload, User $user): ConvivenciaProtocol
    {
        return DB::transaction(function () use ($payload, $user) {
            $protocol = new ConvivenciaProtocol;
            $this->fillProtocol($protocol, $payload, $user, true);
            $protocol->revision = 1;
            $protocol->save();

            [$steps, $nestedLinks] = $this->syncProtocolSteps($protocol, $payload);
            $this->syncPartLinks($protocol, $payload, $steps, $nestedLinks);
            $this->supportService->logStatus($protocol, null, $protocol->status, $user, 'Protocolo creado.', 'created');

            return $this->loadProtocol($protocol);
        });
    }

    public function update(ConvivenciaProtocol $protocol, array $payload, User $user): ConvivenciaProtocol
    {
        return DB::transaction(function () use ($protocol, $payload, $user) {
            $protocol = ConvivenciaProtocol::query()->lockForUpdate()->findOrFail($protocol->id);
            $this->assertExpectedRevision($protocol, $payload['expected_revision'] ?? null);
            $previousStatus = $protocol->status;
            $this->fillProtocol($protocol, $payload, $user, false);
            $protocol->revision = ((int) $protocol->revision) + 1;
            $protocol->save();

            [$steps, $nestedLinks] = $this->syncProtocolSteps($protocol, $payload);
            $this->syncPartLinks($protocol, $payload, $steps, $nestedLinks);
            $this->supportService->logStatus(
                $protocol,
                $previousStatus,
                $protocol->status,
                $user,
                'Definición actualizada. Revisión '.$protocol->revision.'.',
                'definition_updated'
            );

            return $this->loadProtocol($protocol);
        });
    }

    public function archive(ConvivenciaProtocol $protocol, User $user): void
    {
        DB::transaction(function () use ($protocol, $user) {
            $protocol = ConvivenciaProtocol::query()->lockForUpdate()->findOrFail($protocol->id);
            $previousStatus = $protocol->status;
            $protocol->forceFill([
                'status' => 'inactivo',
                'revision' => ((int) $protocol->revision) + 1,
                'updated_by' => $user->id,
            ])->save();
            $this->supportService->logStatus($protocol, $previousStatus, 'inactivo', $user, 'Protocolo archivado.', 'archived');
            $protocol->delete();
        });
    }

    public function activate(array $payload, User $user): ConvivenciaProtocolActivation
    {
        return DB::transaction(function () use ($payload, $user) {
            $protocol = ConvivenciaProtocol::query()
                ->with(['steps.partLinks.part', 'partLinks.part'])
                ->lockForUpdate()
                ->findOrFail($payload['protocol_id']);
            $this->assertProtocolCanBeActivated($protocol);
            [$case, $complaint] = $this->resolveActivationContext($payload, $user);

            $sourceSteps = $protocol->steps->where('active', true)->sortBy('step_order')->values();
            if ($sourceSteps->isEmpty()) {
                throw ValidationException::withMessages(['protocol_id' => 'El protocolo no tiene pasos activos.']);
            }
            if (! empty($payload['current_step_id'])) {
                $requested = $sourceSteps->firstWhere('id', (int) $payload['current_step_id']);
                if (! $requested || (int) $requested->id !== (int) $sourceSteps->first()->id) {
                    throw ValidationException::withMessages([
                        'current_step_id' => 'La activación debe comenzar en el primer paso activo.',
                    ]);
                }
            }

            $activatedAt = now();
            $activation = ConvivenciaProtocolActivation::query()->create([
                'protocol_id' => $protocol->id,
                'case_id' => $case?->id,
                'complaint_id' => $complaint?->id,
                'activated_by' => $user->id,
                'activated_at' => $activatedAt,
                'status' => 'activo',
                'involved_snapshot' => $payload['involved_snapshot'] ?? null,
                'protocol_snapshot' => $this->protocolSnapshot($protocol),
                'progress_percentage' => 0,
                'revision' => 1,
                'actions_taken' => $payload['actions_taken'] ?? null,
                'measures_adopted' => $payload['measures_adopted'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $academicYearId = $case?->academic_year_id ?: $complaint?->academic_year_id;
            $runtimeBySource = collect();
            foreach ($sourceSteps as $index => $sourceStep) {
                $isFirst = $index === 0;
                $deadline = $isFirst
                    ? $this->deadlineForStep($sourceStep, $activatedAt, $academicYearId)
                    : $this->emptyDeadline();
                $runtime = $activation->runtimeSteps()->create([
                    'source_protocol_step_id' => $sourceStep->id,
                    'step_order' => $sourceStep->step_order,
                    'code' => $sourceStep->code,
                    'stage_name' => $sourceStep->stage_name,
                    'description' => $sourceStep->description,
                    'step_type' => $sourceStep->step_type ?: 'gestion',
                    'responsible_label' => $sourceStep->responsible_label,
                    'status' => $isFirst ? 'in_progress' : 'pending',
                    'deadline_value' => $sourceStep->deadline_value ?: $sourceStep->due_days,
                    'deadline_unit' => $sourceStep->deadline_unit ?: 'calendar_days',
                    'deadline_anchor' => $sourceStep->deadline_anchor ?: 'step_started',
                    'can_extend' => (bool) $sourceStep->can_extend,
                    'extension_value' => $sourceStep->extension_value,
                    'extension_unit' => $sourceStep->extension_unit,
                    'started_at' => $isFirst ? $activatedAt : null,
                    'due_at' => $deadline['due_at'],
                    'snapshot' => [
                        'required_documents' => $sourceStep->required_documents,
                        'minimal_actions' => $sourceStep->minimal_actions,
                        'safeguard_measures' => $sourceStep->safeguard_measures,
                        'completion_rule' => $sourceStep->completion_rule,
                        'metadata' => $sourceStep->metadata,
                        'deadline_resolution' => $this->serializableDeadline($deadline),
                    ],
                ]);
                $runtimeBySource->put((int) $sourceStep->id, $runtime);
            }

            foreach ($protocol->partLinks->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $link) {
                $part = $link->part;
                if (! $part || ! $part->active) {
                    continue;
                }
                $runtimeStep = $link->protocol_step_id ? $runtimeBySource->get((int) $link->protocol_step_id) : null;
                $partAnchor = $this->partAnchor($part, $runtimeStep, $activatedAt);
                $deadline = $partAnchor
                    ? $this->deadlineService->calculate($partAnchor, $part->deadline_value, $part->deadline_unit, $academicYearId)
                    : $this->emptyDeadline();
                $startsNow = ! $runtimeStep || $runtimeStep->status === 'in_progress';
                $activation->runtimeParts()->create([
                    'activation_step_id' => $runtimeStep?->id,
                    'source_link_id' => $link->id,
                    'protocol_part_id' => $part->id,
                    'category' => $part->category,
                    'code' => $part->code,
                    'title' => $part->title,
                    'description' => $part->description,
                    'instructions' => $part->instructions,
                    'responsible_label' => $part->responsible_label,
                    'population_scope' => $part->population_scope,
                    'legal_reference' => $part->legal_reference,
                    'sort_order' => $link->sort_order,
                    'is_required' => (bool) $link->is_required,
                    'requires_evidence' => (bool) $part->requires_evidence,
                    'status' => 'pending',
                    'started_at' => $startsNow ? $activatedAt : null,
                    'due_at' => $deadline['due_at'],
                    'snapshot' => [
                        'condition' => $link->condition,
                        'configuration' => $link->configuration,
                        'metadata' => $part->metadata,
                        'deadline_value' => $part->deadline_value,
                        'deadline_unit' => $part->deadline_unit,
                        'deadline_anchor' => $part->deadline_anchor,
                        'deadline_resolution' => $this->serializableDeadline($deadline),
                    ],
                ]);
            }

            $firstRuntime = $runtimeBySource->get((int) $sourceSteps->first()->id);
            $activation->forceFill([
                'current_step_id' => $firstRuntime->source_protocol_step_id,
                'current_activation_step_id' => $firstRuntime->id,
                'current_stage_name' => $firstRuntime->stage_name,
                'due_at' => $firstRuntime->due_at,
            ])->save();
            $activation->logs()->create([
                'protocol_step_id' => $firstRuntime->source_protocol_step_id,
                'created_by' => $user->id,
                'action_type' => 'activacion',
                'stage_name' => $firstRuntime->stage_name,
                'notes' => 'Protocolo activado. Se inicia el primer paso.',
                'due_at' => $firstRuntime->due_at,
            ]);
            $this->supportService->logStatus($activation, null, 'activo', $user, 'Protocolo activado.', 'created');
            $this->syncRelatedStatuses($activation, $user);

            return $this->loadActivation($activation);
        });
    }

    public function updateActivation(ConvivenciaProtocolActivation $activation, array $payload, User $user): ConvivenciaProtocolActivation
    {
        return DB::transaction(function () use ($activation, $payload, $user) {
            $activation = ConvivenciaProtocolActivation::query()->lockForUpdate()->findOrFail($activation->id);
            $this->assertActivationRevision($activation, $payload['revision'] ?? null);
            $this->assertActivationMutable($activation);
            $previousStatus = $activation->status;
            $currentRuntime = $activation->current_activation_step_id
                ? $activation->runtimeSteps()->find($activation->current_activation_step_id)
                : null;

            if (! empty($payload['current_step_id'])) {
                if ((int) $payload['current_step_id'] !== (int) $activation->current_step_id) {
                    throw ValidationException::withMessages([
                        'current_step_id' => 'El avance de pasos es secuencial. Completa el paso activo para continuar.',
                    ]);
                }
            }

            if ($currentRuntime && array_key_exists('current_stage_name', $payload)
                && $payload['current_stage_name'] !== $currentRuntime->stage_name) {
                throw ValidationException::withMessages([
                    'current_stage_name' => 'La etapa se deriva del paso activo y no puede sobrescribirse manualmente.',
                ]);
            }
            if ($currentRuntime && array_key_exists('due_at', $payload)
                && $this->dateTimeChanged($currentRuntime->due_at, $payload['due_at'])) {
                throw ValidationException::withMessages([
                    'due_at' => 'Modifica el plazo desde el paso activo y registra su justificación.',
                ]);
            }

            $nextStatus = $payload['status'] ?? $activation->status;
            if ($nextStatus === 'cerrado') {
                $unresolvedSteps = $activation->runtimeSteps()
                    ->whereNotIn('status', ['completed', 'skipped'])
                    ->count();
                $invalidParts = $this->invalidRequiredParts($activation->runtimeParts()->get());
                if ($unresolvedSteps > 0 || $invalidParts->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'status' => 'Completa secuencialmente el último paso antes de cerrar el protocolo.',
                        'parts' => $invalidParts->pluck('title')->values()->all(),
                    ]);
                }
            }

            $activation->forceFill([
                'status' => $nextStatus,
                'current_stage_name' => $currentRuntime
                    ? $currentRuntime->stage_name
                    : (array_key_exists('current_stage_name', $payload) ? $payload['current_stage_name'] : $activation->current_stage_name),
                'due_at' => $currentRuntime
                    ? $currentRuntime->due_at
                    : (array_key_exists('due_at', $payload) ? $payload['due_at'] : $activation->due_at),
                'actions_taken' => array_key_exists('actions_taken', $payload) ? $payload['actions_taken'] : $activation->actions_taken,
                'measures_adopted' => array_key_exists('measures_adopted', $payload) ? $payload['measures_adopted'] : $activation->measures_adopted,
                'closing_summary' => array_key_exists('closing_summary', $payload) ? $payload['closing_summary'] : $activation->closing_summary,
                'closed_at' => $nextStatus === 'cerrado' ? ($activation->closed_at ?: now()) : null,
                'revision' => ((int) $activation->revision) + 1,
                'updated_by' => $user->id,
            ])->save();
            $this->refreshProgress($activation);
            $activation->logs()->create([
                'protocol_step_id' => $activation->current_step_id,
                'created_by' => $user->id,
                'action_type' => $payload['action_type'] ?? ($nextStatus === 'cerrado' ? 'cierre' : 'avance'),
                'stage_name' => $activation->current_stage_name,
                'notes' => $payload['log_notes'] ?? null,
                'due_at' => $activation->due_at,
                'completed_at' => $payload['completed_at'] ?? ($nextStatus === 'cerrado' ? now() : null),
            ]);
            if ($previousStatus !== $activation->status) {
                $this->supportService->logStatus($activation, $previousStatus, $activation->status, $user);
            }
            $this->syncRelatedStatuses($activation, $user);

            return $this->loadActivation($activation);
        });
    }

    public function updateRuntimeStep(ConvivenciaProtocolActivationStep $step, array $payload, User $user): ConvivenciaProtocolActivation
    {
        if (($payload['status'] ?? null) === 'completed') {
            return $this->completeRuntimeStep($step, $payload, $user);
        }

        return DB::transaction(function () use ($step, $payload, $user) {
            $step = ConvivenciaProtocolActivationStep::query()->lockForUpdate()->findOrFail($step->id);
            $activation = ConvivenciaProtocolActivation::query()->lockForUpdate()->findOrFail($step->activation_id);
            $this->assertActivationRevision($activation, $payload['revision'] ?? null);
            $this->assertActivationMutable($activation);
            if ((int) $activation->current_activation_step_id !== (int) $step->id || in_array($step->status, ['completed', 'skipped'], true)) {
                throw ValidationException::withMessages(['step' => 'Solo se puede editar el paso activo.']);
            }
            $status = $payload['status'] ?? $step->status;
            if (in_array($status, ['completed', 'skipped'], true)) {
                throw ValidationException::withMessages(['status' => 'Usa la acción de completar para avanzar.']);
            }

            $persistedData = (array) $step->data;
            $data = array_key_exists('data', $payload) ? (array) $payload['data'] : $persistedData;
            foreach ([
                'extensions',
                'last_extension',
                'extension_used_value',
                'extension_unit',
                'extension_approved_by',
                'extension_approved_at',
            ] as $systemKey) {
                if (array_key_exists($systemKey, $persistedData)) {
                    $data[$systemKey] = $persistedData[$systemKey];
                } else {
                    unset($data[$systemKey]);
                }
            }
            if (array_key_exists('completion_criteria', $payload)) {
                $data['completion_criteria'] = $payload['completion_criteria'];
            }
            $changes = [
                'status' => $status,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $step->notes,
                'outcome' => array_key_exists('outcome', $payload) ? $payload['outcome'] : $step->outcome,
                'evidence_summary' => array_key_exists('evidence_summary', $payload) ? $payload['evidence_summary'] : $step->evidence_summary,
                'data' => $data ?: null,
                'started_at' => $payload['started_at'] ?? ($status === 'in_progress' ? ($step->started_at ?: now()) : $step->started_at),
            ];
            if (array_key_exists('due_at', $payload) && $this->dateTimeChanged($step->due_at, $payload['due_at'])) {
                $externallyDefined = in_array($step->deadline_unit, ['external', 'external_defined'], true);
                if (! $externallyDefined) {
                    throw ValidationException::withMessages([
                        'due_at' => 'El plazo calculado no puede sobrescribirse. Usa la prórroga configurada para este paso.',
                    ]);
                }
                if (blank($payload['log_notes'] ?? null)) {
                    throw ValidationException::withMessages(['due_at' => 'El plazo externo debe quedar justificado.']);
                }
                $changes['due_at'] = $payload['due_at'];
            } elseif (array_key_exists('extension_value', $payload) && $payload['extension_value'] !== null) {
                if (! $step->can_extend) {
                    throw ValidationException::withMessages(['extension_value' => 'La prórroga debe estar permitida y justificada.']);
                }
                $metadata = (array) data_get($step->snapshot, 'metadata', []);
                $maximum = $step->extension_value ? (int) $step->extension_value : null;
                $extensionValue = (int) $payload['extension_value'];
                $usedExtension = (int) ($persistedData['extension_used_value']
                    ?? data_get($persistedData, 'last_extension.value', 0));
                if ($maximum !== null && ($usedExtension + $extensionValue) > $maximum) {
                    throw ValidationException::withMessages([
                        'extension_value' => "La prórroga máxima acumulada configurada es de {$maximum}.",
                    ]);
                }
                $configuredUnit = $step->extension_unit ?: null;
                $extensionUnit = $payload['extension_unit'] ?? $configuredUnit ?? $step->deadline_unit ?? 'calendar_days';
                if ($configuredUnit && $extensionUnit !== $configuredUnit) {
                    throw ValidationException::withMessages([
                        'extension_unit' => "La prórroga debe expresarse en {$configuredUnit}.",
                    ]);
                }
                if (in_array($extensionUnit, ['external', 'external_defined'], true)) {
                    throw ValidationException::withMessages([
                        'extension_unit' => 'Una prórroga calculada debe usar horas o días.',
                    ]);
                }
                if (($metadata['extension_requires_reason'] ?? false) && blank($payload['log_notes'] ?? null)) {
                    throw ValidationException::withMessages([
                        'log_notes' => 'La definición exige fundamentar esta prórroga.',
                    ]);
                }
                if (($metadata['extension_requires_approval'] ?? false)
                    && ($payload['extension_approved'] ?? false) !== true) {
                    throw ValidationException::withMessages([
                        'extension_approved' => 'La definición exige confirmar la autorización de la prórroga.',
                    ]);
                }
                $deadline = $this->deadlineService->calculate(
                    $step->due_at ?: now(),
                    $extensionValue,
                    $extensionUnit,
                    $this->academicYearId($activation)
                );
                $changes['due_at'] = $deadline['due_at'];
                $extensionRecord = [
                    'value' => $extensionValue,
                    'unit' => $extensionUnit,
                    'approval_confirmed' => (bool) ($payload['extension_approved'] ?? false),
                    'extension_approved_by' => ($payload['extension_approved'] ?? false) ? $user->id : null,
                    'extension_approved_at' => ($payload['extension_approved'] ?? false) ? now()->toIso8601String() : null,
                    'reason' => $payload['log_notes'] ?? null,
                    'applied_at' => now()->toIso8601String(),
                    'applied_by' => $user->id,
                ];
                $extensions = array_values((array) ($persistedData['extensions'] ?? []));
                $extensions[] = $extensionRecord;
                $data['extensions'] = $extensions;
                $data['last_extension'] = $extensionRecord;
                $data['extension_used_value'] = $usedExtension + $extensionValue;
                $data['extension_unit'] = $extensionUnit;
                $changes['data'] = $data;
            }
            $step->forceFill($changes)->save();
            $activation->forceFill([
                'status' => $status === 'blocked' ? 'en_seguimiento' : 'activo',
                'due_at' => $step->due_at,
                'revision' => ((int) $activation->revision) + 1,
                'updated_by' => $user->id,
            ])->save();
            $activation->logs()->create([
                'protocol_step_id' => $step->source_protocol_step_id,
                'created_by' => $user->id,
                'action_type' => $status === 'blocked' ? 'bloqueo' : 'actualizacion_paso',
                'stage_name' => $step->stage_name,
                'notes' => $payload['log_notes'] ?? $payload['notes'] ?? null,
                'due_at' => $step->due_at,
            ]);

            return $this->loadActivation($activation);
        });
    }

    public function completeRuntimeStep(ConvivenciaProtocolActivationStep $step, array $payload, User $user): ConvivenciaProtocolActivation
    {
        return DB::transaction(function () use ($step, $payload, $user) {
            $step = ConvivenciaProtocolActivationStep::query()->lockForUpdate()->findOrFail($step->id);
            $activation = ConvivenciaProtocolActivation::query()->lockForUpdate()->findOrFail($step->activation_id);
            $this->assertActivationRevision($activation, $payload['revision'] ?? null);
            $this->assertActivationMutable($activation);
            if ((int) $activation->current_activation_step_id !== (int) $step->id || $step->status === 'completed') {
                throw ValidationException::withMessages(['step' => 'Solo el paso activo puede completarse.']);
            }

            $rule = (array) data_get($step->snapshot, 'completion_rule', []);
            $blocking = $this->invalidRequiredParts(
                $step->parts()->get(),
                (bool) ($rule['requires_all_parts'] ?? false)
            );
            if ($blocking->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'parts' => ['Completa correctamente las partes obligatorias: '.$blocking->pluck('title')->implode(', ').'.'],
                ]);
            }
            $notes = array_key_exists('notes', $payload) ? $payload['notes'] : $step->notes;
            $outcome = array_key_exists('outcome', $payload) ? $payload['outcome'] : $step->outcome;
            $evidence = array_key_exists('evidence_summary', $payload) ? $payload['evidence_summary'] : $step->evidence_summary;
            $data = array_key_exists('data', $payload) ? (array) $payload['data'] : (array) $step->data;
            if (array_key_exists('completion_criteria', $payload)) {
                $data['completion_criteria'] = $payload['completion_criteria'];
            }
            $this->validateCompletionRule(
                $rule,
                $notes,
                $outcome,
                $evidence,
                (array) data_get($data, 'completion_criteria', [])
            );

            $completedAt = now();
            $step->forceFill([
                'status' => 'completed',
                'notes' => $notes,
                'outcome' => $outcome,
                'evidence_summary' => $evidence,
                'data' => $data ?: null,
                'completed_at' => $completedAt,
                'completed_by' => $user->id,
            ])->save();
            $next = $activation->runtimeSteps()->where('step_order', '>', $step->step_order)
                ->where('status', 'pending')->orderBy('step_order')->first();
            $previousStatus = $activation->status;
            if ($next) {
                $this->startRuntimeStep($activation, $next, $completedAt);
                $activation->forceFill([
                    'current_step_id' => $next->source_protocol_step_id,
                    'current_activation_step_id' => $next->id,
                    'current_stage_name' => $next->stage_name,
                    'due_at' => $next->due_at,
                    'status' => 'activo',
                    'revision' => ((int) $activation->revision) + 1,
                    'updated_by' => $user->id,
                ])->save();
            } else {
                $globalBlocking = $this->invalidRequiredParts(
                    $activation->runtimeParts()->whereNull('activation_step_id')->get()
                );
                if ($globalBlocking->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'parts' => ['Completa correctamente las partes generales obligatorias: '.$globalBlocking->pluck('title')->implode(', ').'.'],
                    ]);
                }
                $activation->forceFill([
                    'current_step_id' => $step->source_protocol_step_id,
                    'current_activation_step_id' => $step->id,
                    'current_stage_name' => $step->stage_name,
                    'due_at' => null,
                    'status' => 'cerrado',
                    'closed_at' => $completedAt,
                    'revision' => ((int) $activation->revision) + 1,
                    'updated_by' => $user->id,
                ])->save();
            }
            $this->refreshProgress($activation);
            $activation->logs()->create([
                'protocol_step_id' => $step->source_protocol_step_id,
                'created_by' => $user->id,
                'action_type' => $next ? 'paso_completado' : 'cierre',
                'stage_name' => $step->stage_name,
                'notes' => $payload['log_notes'] ?? $notes,
                'due_at' => $step->due_at,
                'completed_at' => $completedAt,
            ]);
            if ($previousStatus !== $activation->status) {
                $this->supportService->logStatus($activation, $previousStatus, $activation->status, $user);
            }
            $this->syncRelatedStatuses($activation, $user);

            return $this->loadActivation($activation);
        });
    }

    public function updateRuntimePart(ConvivenciaProtocolActivationPart $part, array $payload, User $user): ConvivenciaProtocolActivation
    {
        return DB::transaction(function () use ($part, $payload, $user) {
            $part = ConvivenciaProtocolActivationPart::query()->lockForUpdate()->findOrFail($part->id);
            $activation = ConvivenciaProtocolActivation::query()->lockForUpdate()->findOrFail($part->activation_id);
            $this->assertActivationRevision($activation, $payload['revision'] ?? null);
            $this->assertActivationMutable($activation);
            if ($part->activation_step_id
                && (int) $part->activation_step_id !== (int) $activation->current_activation_step_id) {
                throw ValidationException::withMessages(['part' => 'Solo se pueden editar partes del paso activo.']);
            }
            $status = $payload['status'] ?? $part->status;
            $notes = array_key_exists('notes', $payload) ? $payload['notes'] : $part->notes;
            $evidence = array_key_exists('evidence_summary', $payload) ? $payload['evidence_summary'] : $part->evidence_summary;
            $data = array_key_exists('data', $payload) ? $payload['data'] : $part->data;
            $configuration = (array) data_get($part->snapshot, 'configuration', []);
            $condition = (array) data_get($part->snapshot, 'condition', []);
            if ($status === 'not_applicable' && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'Debes justificar por qué no resulta aplicable.']);
            }
            if ($status === 'not_applicable' && $condition
                && ! ($configuration['blocked_for_preschool_child'] ?? false)) {
                $submittedData = (array) ($payload['data'] ?? []);
                $conditionExplicitlyFalse = array_key_exists('condition_confirmed', $submittedData)
                    && $submittedData['condition_confirmed'] === false;
                $notMetConfirmed = ($submittedData['condition_not_met_confirmed'] ?? false) === true
                    && ($submittedData['condition_confirmed'] ?? false) !== true;
                if (! $conditionExplicitlyFalse && ! $notMetConfirmed) {
                    throw ValidationException::withMessages([
                        'data.condition_confirmed' => 'Confirma explícitamente que la condición no se cumple antes de registrar No aplica.',
                    ]);
                }
                $data['condition_confirmed'] = false;
                $data['condition_not_met_confirmed'] = true;
            }
            if ($status === 'not_applicable' && $part->is_required && ! $condition
                && ! ($configuration['allow_not_applicable'] ?? false)
                && ! ($configuration['blocked_for_preschool_child'] ?? false)) {
                throw ValidationException::withMessages([
                    'status' => 'Una parte obligatoria incondicional no puede marcarse como No aplica.',
                ]);
            }
            if (($configuration['blocked_for_preschool_child'] ?? false) && $status !== 'not_applicable') {
                throw ValidationException::withMessages([
                    'status' => 'Esta parte está bloqueada para párvulos. Registra No aplica y fundamenta la decisión.',
                ]);
            }
            if ($condition && in_array($status, ['in_progress', 'completed'], true)
                && data_get($data, 'condition_confirmed') !== true) {
                throw ValidationException::withMessages([
                    'data.condition_confirmed' => 'Debes confirmar que se verificó la condición antes de ejecutar esta parte.',
                ]);
            }
            if ($status === 'completed' && $part->requires_evidence && blank($evidence) && blank(data_get($data, 'evidence'))) {
                throw ValidationException::withMessages(['evidence_summary' => 'Esta parte exige evidencia.']);
            }
            if (array_key_exists('due_at', $payload) && $this->dateTimeChanged($part->due_at, $payload['due_at']) && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'Debes justificar el cambio de plazo en las notas.']);
            }
            $part->forceFill([
                'status' => $status,
                'notes' => $notes,
                'evidence_summary' => $evidence,
                'outcome' => array_key_exists('outcome', $payload) ? $payload['outcome'] : $part->outcome,
                'data' => $data,
                'started_at' => $payload['started_at'] ?? (in_array($status, ['in_progress', 'completed'], true) ? ($part->started_at ?: now()) : $part->started_at),
                'due_at' => array_key_exists('due_at', $payload) ? $payload['due_at'] : $part->due_at,
                'completed_at' => $status === 'completed' ? ($part->completed_at ?? now()) : null,
                'completed_by' => $status === 'completed' ? $user->id : null,
            ])->save();
            $activation->forceFill([
                'revision' => ((int) $activation->revision) + 1,
                'updated_by' => $user->id,
            ])->save();
            $part->loadMissing('activationStep');
            $activation->logs()->create([
                'protocol_step_id' => $part->activationStep?->source_protocol_step_id,
                'created_by' => $user->id,
                'action_type' => 'actualizacion_parte',
                'stage_name' => $part->activationStep?->stage_name,
                'notes' => $part->title.': '.($notes ?: $status),
                'due_at' => $part->due_at,
                'completed_at' => $part->completed_at,
            ]);

            return $this->loadActivation($activation);
        });
    }

    public function progress(
        ConvivenciaProtocolActivation $activation,
        ?User $user = null,
        bool $materializeLegacy = true,
    ): array {
        $activation->loadMissing(['runtimeSteps', 'currentActivationStep', 'currentStep']);
        if ($activation->runtimeSteps->isEmpty()) {
            $activation->loadMissing('protocol.steps');
        }

        return $this->buildProgress($activation);
    }

    public function presentActivation(ConvivenciaProtocolActivation $activation, User $user): ConvivenciaProtocolActivation
    {
        return $this->loadActivation($activation);
    }

    public function materializeLegacyActivation(
        ConvivenciaProtocolActivation $activation,
        User $user,
        int $expectedRevision,
    ): ConvivenciaProtocolActivation {
        $activation = $this->materializeLegacyRuntime($activation, $user, $expectedRevision);

        return $this->loadActivation($activation);
    }

    private function fillProtocol(ConvivenciaProtocol $protocol, array $payload, User $user, bool $creating): void
    {
        $type = ! empty($payload['protocol_type_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['protocol_type_item_id']) : null;
        $criticality = ! empty($payload['criticality_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['criticality_item_id']) : null;
        $protocol->fill([
            'code' => array_key_exists('code', $payload) ? $payload['code'] : $protocol->code,
            'version_label' => $payload['version_label'] ?? $protocol->version_label,
            'regulatory_source' => $payload['regulatory_source'] ?? $protocol->regulatory_source,
            'education_scope' => $payload['education_scope'] ?? $protocol->education_scope,
            'legal_reference' => $payload['legal_reference'] ?? $protocol->legal_reference,
            'source_reference' => $payload['source_reference'] ?? $protocol->source_reference,
            'effective_from' => $payload['effective_from'] ?? $protocol->effective_from,
            'effective_to' => $payload['effective_to'] ?? $protocol->effective_to,
            'published_at' => $payload['published_at'] ?? $protocol->published_at,
            'metadata' => array_key_exists('metadata', $payload) ? $payload['metadata'] : $protocol->metadata,
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

    /**
     * @return array{0: Collection<int, ConvivenciaProtocolStep>, 1: array<int, array<string, mixed>>}
     */
    private function syncProtocolSteps(ConvivenciaProtocol $protocol, array $payload): array
    {
        $existing = $protocol->steps()->get();
        if (! array_key_exists('steps', $payload)) {
            return [$existing->sortBy('step_order')->values(), []];
        }
        $definitions = array_values($payload['steps'] ?? []);
        $existingById = $existing->keyBy('id');
        $existingByCode = $existing->filter(fn ($step) => filled($step->code))->keyBy('code');
        $existingByOrder = $existing->keyBy('step_order');
        $shift = max(1000, ((int) $existing->max('step_order')) + count($definitions) + 100);
        foreach ($existing->values() as $index => $step) {
            $step->forceFill(['step_order' => $shift + $index])->save();
        }

        $saved = collect();
        $usedIds = [];
        $nestedLinks = [];
        foreach ($definitions as $index => $definition) {
            $requestedOrder = (int) ($definition['step_order'] ?? ($index + 1));
            $step = null;
            if (! empty($definition['id'])) {
                $step = $existingById->get((int) $definition['id']);
                if (! $step) {
                    throw ValidationException::withMessages(["steps.{$index}.id" => 'El paso no pertenece al protocolo.']);
                }
            } elseif (! empty($definition['code'])) {
                $step = $existingByCode->get($definition['code']);
            } else {
                $step = $existingByOrder->get($requestedOrder);
            }
            $step ??= new ConvivenciaProtocolStep(['protocol_id' => $protocol->id]);
            $deadlineValue = $definition['deadline_value'] ?? $definition['due_days'] ?? $step->deadline_value ?? $step->due_days;
            $step->fill([
                'protocol_id' => $protocol->id,
                'step_order' => $requestedOrder,
                'code' => $definition['code'] ?? $step->code ?? $this->uniqueStepCode($protocol, $definition['stage_name'], $saved),
                'stage_name' => $definition['stage_name'],
                'description' => $definition['description'] ?? null,
                'step_type' => $definition['step_type'] ?? 'gestion',
                'responsible_label' => $definition['responsible_label'] ?? null,
                'due_days' => $definition['due_days'] ?? (
                    in_array($definition['deadline_unit'] ?? $step->deadline_unit ?? 'calendar_days', ['calendar_days', 'business_days', 'school_days'], true)
                        ? $deadlineValue
                        : null
                ),
                'deadline_value' => $deadlineValue,
                'deadline_unit' => $definition['deadline_unit'] ?? $step->deadline_unit ?? 'calendar_days',
                'deadline_anchor' => $definition['deadline_anchor'] ?? $step->deadline_anchor ?? 'step_started',
                'can_extend' => (bool) ($definition['can_extend'] ?? false),
                'extension_value' => $definition['extension_value'] ?? null,
                'extension_unit' => $definition['extension_unit'] ?? null,
                'completion_rule' => $definition['completion_rule'] ?? null,
                'active' => (bool) ($definition['active'] ?? true),
                'metadata' => $definition['metadata'] ?? null,
                'required_documents' => $definition['required_documents'] ?? null,
                'minimal_actions' => $definition['minimal_actions'] ?? null,
                'safeguard_measures' => $definition['safeguard_measures'] ?? null,
            ])->save();
            $usedIds[] = (int) $step->id;
            $saved->push($step);
            foreach (($definition['part_links'] ?? $definition['parts'] ?? []) as $link) {
                $nestedLinks[] = array_merge($link, ['protocol_step_id' => $step->id, 'step_code' => $step->code]);
            }
        }
        foreach ($existing->whereNotIn('id', $usedIds) as $step) {
            $referenced = $step->activationLogs()->exists()
                || $step->activationSteps()->exists()
                || ConvivenciaProtocolActivation::query()->where('current_step_id', $step->id)->exists();
            $referenced ? $step->forceFill(['active' => false])->save() : $step->delete();
        }

        return [$saved->sortBy('step_order')->values(), $nestedLinks];
    }

    private function syncPartLinks(ConvivenciaProtocol $protocol, array $payload, Collection $steps, array $nestedLinks): void
    {
        $hasTop = array_key_exists('part_links', $payload) || array_key_exists('parts', $payload);
        $hasNested = collect($payload['steps'] ?? [])->contains(
            fn ($step) => array_key_exists('part_links', $step) || array_key_exists('parts', $step)
        );
        if (! $hasTop && ! $hasNested) {
            return;
        }
        $definitions = array_merge(array_values($payload['part_links'] ?? $payload['parts'] ?? []), $nestedLinks);
        $existing = $protocol->partLinks()->get()->keyBy('id');
        $stepsById = $steps->keyBy('id');
        $stepsByCode = $steps->filter(fn ($step) => filled($step->code))->keyBy('code');
        $used = [];
        foreach ($definitions as $index => $definition) {
            $link = ! empty($definition['id']) ? $existing->get((int) $definition['id']) : null;
            if (! empty($definition['id']) && ! $link) {
                throw ValidationException::withMessages(["part_links.{$index}.id" => 'El vínculo no pertenece al protocolo.']);
            }
            $part = ConvivenciaProtocolPart::query()->where('active', true)->find($definition['protocol_part_id']);
            if (! $part) {
                throw ValidationException::withMessages(["part_links.{$index}.protocol_part_id" => 'La parte no está activa.']);
            }
            $step = null;
            if (! empty($definition['protocol_step_id'])) {
                $step = $stepsById->get((int) $definition['protocol_step_id']);
            } elseif (! empty($definition['step_code'])) {
                $step = $stepsByCode->get($definition['step_code']);
            }
            if ((! empty($definition['protocol_step_id']) || ! empty($definition['step_code'])) && ! $step) {
                throw ValidationException::withMessages(["part_links.{$index}.protocol_step_id" => 'El paso no pertenece al protocolo.']);
            }
            $link ??= new ConvivenciaProtocolPartLink(['protocol_id' => $protocol->id]);
            $link->fill([
                'protocol_id' => $protocol->id,
                'protocol_step_id' => $step?->id,
                'protocol_part_id' => $part->id,
                'sort_order' => $definition['sort_order'] ?? ($index + 1),
                'is_required' => (bool) ($definition['is_required'] ?? true),
                'condition' => $definition['condition'] ?? null,
                'configuration' => $definition['configuration'] ?? null,
            ])->save();
            $used[] = $link->id;
        }
        $protocol->partLinks()->whereNotIn('id', $used)->delete();
    }

    private function assertProtocolCanBeActivated(ConvivenciaProtocol $protocol): void
    {
        if ($protocol->status !== 'activo') {
            throw ValidationException::withMessages(['protocol_id' => 'Solo se pueden activar protocolos activos.']);
        }
        if ($protocol->effective_from && $protocol->effective_from->isFuture()) {
            throw ValidationException::withMessages(['protocol_id' => 'El protocolo aún no está vigente.']);
        }
        if ($protocol->effective_to && $protocol->effective_to->lt(today())) {
            throw ValidationException::withMessages(['protocol_id' => 'El protocolo ya no está vigente.']);
        }
    }

    private function resolveActivationContext(array $payload, User $user): array
    {
        $case = ! empty($payload['case_id']) ? ConvivenciaCase::query()->findOrFail($payload['case_id']) : null;
        $complaint = ! empty($payload['complaint_id']) ? ConvivenciaComplaint::query()->findOrFail($payload['complaint_id']) : null;
        if ($case && ! $this->accessService->canViewCase($user, $case)) {
            throw new AuthorizationException('No tienes acceso al caso indicado.');
        }
        if ($complaint && ! $this->accessService->canViewComplaint($user, $complaint)) {
            throw new AuthorizationException('No tienes acceso a la denuncia indicada.');
        }
        if ($case && $complaint && $complaint->case_id && (int) $complaint->case_id !== (int) $case->id) {
            throw ValidationException::withMessages(['complaint_id' => 'La denuncia pertenece a otro caso.']);
        }

        return [$case, $complaint];
    }

    private function startRuntimeStep(ConvivenciaProtocolActivation $activation, ConvivenciaProtocolActivationStep $step, Carbon $startedAt): void
    {
        $deadline = $this->deadlineService->calculate(
            $step->deadline_anchor === 'activation_started' ? Carbon::parse($activation->activated_at) : $startedAt,
            $step->deadline_value,
            $step->deadline_unit,
            $this->academicYearId($activation)
        );
        $snapshot = $step->snapshot ?? [];
        $snapshot['deadline_resolution'] = $this->serializableDeadline($deadline);
        $step->forceFill([
            'status' => 'in_progress',
            'started_at' => $startedAt,
            'due_at' => $deadline['due_at'],
            'snapshot' => $snapshot,
        ])->save();
        $step->loadMissing('parts');
        foreach ($step->parts as $part) {
            $anchor = data_get($part->snapshot, 'deadline_anchor') === 'activation_started'
                ? Carbon::parse($activation->activated_at) : $startedAt;
            $partDeadline = $this->deadlineService->calculate(
                $anchor,
                data_get($part->snapshot, 'deadline_value'),
                data_get($part->snapshot, 'deadline_unit'),
                $this->academicYearId($activation)
            );
            $partSnapshot = $part->snapshot ?? [];
            $partSnapshot['deadline_resolution'] = $this->serializableDeadline($partDeadline);
            $part->forceFill(['started_at' => $startedAt, 'due_at' => $partDeadline['due_at'], 'snapshot' => $partSnapshot])->save();
        }
    }

    private function partAnchor(ConvivenciaProtocolPart $part, ?ConvivenciaProtocolActivationStep $runtimeStep, Carbon $activatedAt): ?Carbon
    {
        if ($part->deadline_anchor === 'activation_started' || ! $runtimeStep) {
            return $activatedAt;
        }

        return $runtimeStep->started_at ? Carbon::parse($runtimeStep->started_at) : null;
    }

    private function deadlineForStep(ConvivenciaProtocolStep $step, Carbon $anchor, ?int $academicYearId): array
    {
        return $this->deadlineService->calculate(
            $anchor,
            $step->deadline_value ?: $step->due_days,
            $step->deadline_unit ?: 'calendar_days',
            $academicYearId
        );
    }

    private function academicYearId(ConvivenciaProtocolActivation $activation): ?int
    {
        $activation->loadMissing(['case:id,academic_year_id', 'complaint:id,academic_year_id']);

        return $activation->case?->academic_year_id ?: $activation->complaint?->academic_year_id;
    }

    private function refreshProgress(ConvivenciaProtocolActivation $activation): void
    {
        $steps = $activation->runtimeSteps()->get();
        $resolved = $steps->whereIn('status', ['completed', 'skipped'])->count();
        $activation->forceFill([
            'progress_percentage' => $steps->isNotEmpty() ? round(($resolved / $steps->count()) * 100, 2) : 0,
        ])->save();
    }

    private function buildProgress(ConvivenciaProtocolActivation $activation): array
    {
        $steps = $activation->runtimeSteps;
        if ($steps->isEmpty()) {
            $total = $activation->protocol?->steps?->where('active', true)->count() ?? 0;
            $order = $activation->currentStep?->step_order;

            return [
                'current_order' => $order,
                'total_steps' => $total,
                'completed_steps' => 0,
                'percentage' => (float) ($activation->progress_percentage ?? 0),
                'label' => $order && $total ? "Paso {$order} de {$total} · {$activation->current_stage_name}" : ($activation->current_stage_name ?: 'Sin paso registrado'),
                'deadline_status' => $this->deadlineStatus($activation->due_at, $activation->closed_at),
                'due_at' => $activation->due_at,
            ];
        }
        $current = $steps->firstWhere('id', (int) $activation->current_activation_step_id)
            ?: $steps->firstWhere('status', 'in_progress') ?: $steps->last();
        $completed = $steps->where('status', 'completed')->count();
        $resolved = $steps->whereIn('status', ['completed', 'skipped'])->count();
        $percentage = round(($resolved / max(1, $steps->count())) * 100, 2);

        return [
            'current_order' => $current?->step_order,
            'total_steps' => $steps->count(),
            'completed_steps' => $completed,
            'percentage' => $percentage,
            'label' => $current ? "Paso {$current->step_order} de {$steps->count()} · {$current->stage_name}" : 'Protocolo sin pasos',
            'deadline_status' => $this->deadlineStatus($current?->due_at, $current?->completed_at),
            'due_at' => $current?->due_at,
        ];
    }

    private function deadlineStatus($dueAt, $completedAt = null): string
    {
        if (! $dueAt) {
            return 'no_deadline';
        }
        $due = Carbon::parse($dueAt);
        if ($completedAt) {
            return Carbon::parse($completedAt)->lte($due) ? 'completed_on_time' : 'completed_late';
        }
        if ($due->isPast()) {
            return 'overdue';
        }

        return now()->diffInHours($due, false) <= 48 ? 'due_soon' : 'on_track';
    }

    private function materializeLegacyRuntime(
        ConvivenciaProtocolActivation $activation,
        User $user,
        int $expectedRevision,
    ): ConvivenciaProtocolActivation {
        return DB::transaction(function () use ($activation, $user, $expectedRevision) {
            $activation = ConvivenciaProtocolActivation::query()->lockForUpdate()->findOrFail($activation->id);
            $this->assertActivationRevision($activation, $expectedRevision);
            if ($activation->runtimeSteps()->exists()) {
                return $activation;
            }

            $protocol = ConvivenciaProtocol::withTrashed()
                ->with(['steps.partLinks.part', 'partLinks.part'])
                ->find($activation->protocol_id);
            $activatedAt = $activation->activated_at ? Carbon::parse($activation->activated_at) : Carbon::parse($activation->created_at);
            $isClosed = $activation->status === 'cerrado';
            $runtimeBySource = collect();
            $sourceSteps = $protocol?->steps
                ?->filter(fn (ConvivenciaProtocolStep $step) => $step->active || (int) $step->id === (int) $activation->current_step_id)
                ->sortBy('step_order')->values() ?? collect();

            if ($sourceSteps->isEmpty()) {
                $currentRuntime = $activation->runtimeSteps()->create([
                    'source_protocol_step_id' => null,
                    'step_order' => 1,
                    'code' => 'legacy_stage',
                    'stage_name' => $activation->current_stage_name ?: 'Gestión histórica',
                    'description' => 'Etapa recuperada de una activación anterior al flujo versionado.',
                    'step_type' => 'legacy',
                    'status' => $isClosed ? 'completed' : 'in_progress',
                    'started_at' => $activatedAt,
                    'due_at' => $isClosed ? null : $activation->due_at,
                    'completed_at' => $isClosed ? $activation->closed_at : null,
                    'completed_by' => $isClosed ? $activation->updated_by : null,
                    'snapshot' => [
                        'legacy_materialization' => true,
                        'warning' => 'No existía una definición granular disponible al crear esta activación.',
                        'deadline_resolution' => [
                            'due_at' => $isClosed ? null : optional($activation->due_at)->toIso8601String(),
                            'source' => 'legacy_preserved',
                            'fallback_used' => false,
                            'fallback_reason' => null,
                        ],
                    ],
                ]);
            } else {
                $currentSource = $sourceSteps->firstWhere('id', (int) $activation->current_step_id)
                    ?: $sourceSteps->firstWhere('stage_name', $activation->current_stage_name)
                    ?: $sourceSteps->first();
                $currentOrder = $isClosed ? (int) $sourceSteps->max('step_order') : (int) $currentSource->step_order;

                foreach ($sourceSteps as $sourceStep) {
                    $isCurrent = (int) $sourceStep->step_order === $currentOrder;
                    $wasResolved = $isClosed || (int) $sourceStep->step_order < $currentOrder;
                    $status = $wasResolved ? 'completed' : ($isCurrent ? 'in_progress' : 'pending');
                    $runtime = $activation->runtimeSteps()->create([
                        'source_protocol_step_id' => $sourceStep->id,
                        'step_order' => $sourceStep->step_order,
                        'code' => $sourceStep->code,
                        'stage_name' => $sourceStep->stage_name,
                        'description' => $sourceStep->description,
                        'step_type' => $sourceStep->step_type ?: 'gestion',
                        'responsible_label' => $sourceStep->responsible_label,
                        'status' => $status,
                        'deadline_value' => $sourceStep->deadline_value ?: $sourceStep->due_days,
                        'deadline_unit' => $sourceStep->deadline_unit,
                        'deadline_anchor' => $sourceStep->deadline_anchor,
                        'can_extend' => (bool) $sourceStep->can_extend,
                        'extension_value' => $sourceStep->extension_value,
                        'extension_unit' => $sourceStep->extension_unit,
                        'started_at' => $status === 'pending' ? null : $activatedAt,
                        'due_at' => $isCurrent && ! $isClosed ? $activation->due_at : null,
                        'completed_at' => null,
                        'completed_by' => null,
                        'notes' => $wasResolved ? 'Estado de avance recuperado desde la activación histórica.' : null,
                        'snapshot' => [
                            'required_documents' => $sourceStep->required_documents,
                            'minimal_actions' => $sourceStep->minimal_actions,
                            'safeguard_measures' => $sourceStep->safeguard_measures,
                            'completion_rule' => $sourceStep->completion_rule,
                            'metadata' => $sourceStep->metadata,
                            'legacy_materialization' => true,
                            'definition_revision_used' => $protocol?->revision,
                            'deadline_resolution' => [
                                'due_at' => $isCurrent && ! $isClosed ? optional($activation->due_at)->toIso8601String() : null,
                                'source' => $isCurrent ? 'legacy_preserved' : 'legacy_unknown',
                                'fallback_used' => false,
                                'fallback_reason' => null,
                            ],
                        ],
                    ]);
                    $runtimeBySource->put((int) $sourceStep->id, $runtime);
                }
                $currentRuntime = $isClosed
                    ? $runtimeBySource->last()
                    : ($runtimeBySource->get((int) $currentSource->id) ?: $runtimeBySource->last());

                foreach ($protocol?->partLinks ?? [] as $link) {
                    $part = $link->part;
                    if (! $part) {
                        continue;
                    }
                    $runtimeStep = $link->protocol_step_id ? $runtimeBySource->get((int) $link->protocol_step_id) : null;
                    $resolvedHistorically = $isClosed || ($runtimeStep && $runtimeStep->status === 'completed');
                    $started = ! $runtimeStep || in_array($runtimeStep->status, ['in_progress', 'completed'], true);
                    $activation->runtimeParts()->create([
                        'activation_step_id' => $runtimeStep?->id,
                        'source_link_id' => $link->id,
                        'protocol_part_id' => $part->id,
                        'category' => $part->category,
                        'code' => $part->code,
                        'title' => $part->title,
                        'description' => $part->description,
                        'instructions' => $part->instructions,
                        'responsible_label' => $part->responsible_label,
                        'population_scope' => $part->population_scope,
                        'legal_reference' => $part->legal_reference,
                        'sort_order' => $link->sort_order,
                        'is_required' => (bool) $link->is_required,
                        'requires_evidence' => (bool) $part->requires_evidence,
                        'status' => $resolvedHistorically ? 'not_applicable' : 'pending',
                        'started_at' => $started ? $activatedAt : null,
                        'due_at' => null,
                        'notes' => $resolvedHistorically
                            ? 'Sin estado granular histórico; se conserva como antecedente migrado, no como cumplimiento acreditado.'
                            : null,
                        'snapshot' => [
                            'condition' => $link->condition,
                            'configuration' => $link->configuration,
                            'metadata' => $part->metadata,
                            'deadline_value' => $part->deadline_value,
                            'deadline_unit' => $part->deadline_unit,
                            'deadline_anchor' => $part->deadline_anchor,
                            'legacy_materialization' => true,
                            'definition_revision_used' => $protocol?->revision,
                            'deadline_resolution' => [
                                'due_at' => null,
                                'source' => 'legacy_unknown',
                                'fallback_used' => false,
                                'fallback_reason' => null,
                            ],
                        ],
                    ]);
                }
            }

            $protocolSnapshot = (array) ($activation->protocol_snapshot ?: ($protocol ? $this->protocolSnapshot($protocol) : []));
            $protocolSnapshot['legacy_materialization'] = [
                'materialized_at' => now()->toIso8601String(),
                'definition_revision_used' => $protocol?->revision,
                'warning' => 'La ruta granular fue reconstruida desde la definición vigente; no reemplaza los antecedentes históricos.',
            ];
            $activation->forceFill([
                'current_step_id' => $currentRuntime->source_protocol_step_id,
                'current_activation_step_id' => $currentRuntime->id,
                'current_stage_name' => $currentRuntime->stage_name,
                'due_at' => $isClosed ? null : ($currentRuntime->due_at ?: $activation->due_at),
                'protocol_snapshot' => $protocolSnapshot,
                'revision' => ((int) $activation->revision) + 1,
                'updated_by' => $user->id,
            ])->save();
            $this->refreshProgress($activation);
            $activation->logs()->create([
                'protocol_step_id' => $currentRuntime->source_protocol_step_id,
                'created_by' => $user->id,
                'action_type' => 'materializacion_legacy',
                'stage_name' => $currentRuntime->stage_name,
                'notes' => 'Ruta granular reconstruida de forma diferida para conservar la operatividad de una activación histórica.',
                'due_at' => $activation->due_at,
            ]);
            $this->supportService->logStatus(
                $activation,
                $activation->status,
                $activation->status,
                $user,
                'Ruta granular histórica materializada sin alterar el estado del caso.',
                'runtime_materialized'
            );

            return $activation;
        });
    }

    private function syncRelatedStatuses(ConvivenciaProtocolActivation $activation, User $user): void
    {
        if ($activation->case) {
            $case = $activation->case;
            $previous = $case->status;
            $open = $case->protocolActivations()->whereIn('status', ['activo', 'en_seguimiento', 'vencido'])->exists();
            $next = $open ? 'con_protocolo_activo' : ($activation->status === 'cerrado' ? 'en_seguimiento' : $case->status);
            $case->forceFill(['status' => $next, 'updated_by' => $user->id])->save();
            if ($previous !== $next) {
                $this->supportService->logStatus($case, $previous, $next, $user, 'Estado actualizado por activación de protocolo.');
            }
        }
        if ($activation->complaint) {
            $complaint = $activation->complaint;
            $previous = $complaint->status;
            $open = $complaint->protocolActivations()->whereIn('status', ['activo', 'en_seguimiento', 'vencido'])->exists();
            $next = $open ? 'protocolo_activado' : ($activation->status === 'cerrado' ? 'cerrada' : $complaint->status);
            $complaint->forceFill(['status' => $next, 'updated_by' => $user->id])->save();
            if ($previous !== $next) {
                $this->supportService->logStatus($complaint, $previous, $next, $user, 'Estado actualizado por activación de protocolo.');
            }
        }
    }

    private function loadProtocol(ConvivenciaProtocol $protocol): ConvivenciaProtocol
    {
        return $protocol->fresh([
            'type:id,name',
            'criticality:id,name,color',
            'steps.partLinks.part',
            'partLinks.part',
            'statusLogs.changedBy:id,name',
        ])->loadCount(['steps', 'partLinks']);
    }

    private function loadActivation(ConvivenciaProtocolActivation $activation): ConvivenciaProtocolActivation
    {
        $activation = $activation->fresh([
            'protocol:id,code,name,revision,status,deleted_at',
            'protocol.steps',
            'case:id,folio,status,academic_year_id',
            'complaint:id,folio,status,academic_year_id',
            'currentStep:id,stage_name,step_order',
            'currentActivationStep',
            'activatedBy:id,name',
            'logs.createdBy:id,name',
            'logs.protocolStep:id,stage_name',
            'runtimeSteps.completedBy:id,name',
            'runtimeSteps.parts.completedBy:id,name',
            'runtimeParts.completedBy:id,name',
        ]);
        if ($activation->runtimeSteps->isEmpty()) {
            $sourceSteps = $activation->protocol?->steps?->where('active', true)->sortBy('step_order')->values() ?? collect();
            $currentOrder = $activation->currentStep?->step_order
                ?: $sourceSteps->firstWhere('stage_name', $activation->current_stage_name)?->step_order
                ?: $sourceSteps->first()?->step_order;
            $legacySteps = $sourceSteps->map(function (ConvivenciaProtocolStep $sourceStep) use ($activation, $currentOrder) {
                $resolved = $activation->status === 'cerrado' || (int) $sourceStep->step_order < (int) $currentOrder;
                $current = $activation->status !== 'cerrado' && (int) $sourceStep->step_order === (int) $currentOrder;

                return [
                    'id' => null,
                    'source_protocol_step_id' => $sourceStep->id,
                    'step_order' => $sourceStep->step_order,
                    'code' => $sourceStep->code,
                    'stage_name' => $sourceStep->stage_name,
                    'description' => $sourceStep->description,
                    'step_type' => $sourceStep->step_type,
                    'responsible_label' => $sourceStep->responsible_label,
                    'status' => $resolved ? 'completed' : ($current ? 'in_progress' : 'pending'),
                    'due_at' => $current ? $activation->due_at : null,
                    'parts' => [],
                    'read_only' => true,
                ];
            })->all();
            $activation->setAttribute('steps', $legacySteps);
            $activation->setAttribute('legacy_mode', true);
            $activation->setAttribute('runtime_materialized', false);
            $activation->setAttribute(
                'runtime_materialization_endpoint',
                "/api/convivencia/protocol-activations/{$activation->id}/materialize-runtime"
            );
        } else {
            $activation->setAttribute('legacy_mode', false);
            $activation->setAttribute('runtime_materialized', true);
        }
        $activation->runtimeSteps->each(function (ConvivenciaProtocolActivationStep $step) use ($activation) {
            $step->setAttribute('revision', (int) $activation->revision);
            $step->parts->each(fn (ConvivenciaProtocolActivationPart $part) => $part->setAttribute('revision', (int) $activation->revision));
        });
        $activation->runtimeParts->each(
            fn (ConvivenciaProtocolActivationPart $part) => $part->setAttribute('revision', (int) $activation->revision)
        );
        $activation->setAttribute('progress', $this->buildProgress($activation));

        return $activation;
    }

    private function protocolSnapshot(ConvivenciaProtocol $protocol): array
    {
        return [
            'id' => $protocol->id,
            'code' => $protocol->code,
            'name' => $protocol->name,
            'description' => $protocol->description,
            'revision' => $protocol->revision,
            'version_label' => $protocol->version_label,
            'protocol_type_item_id' => $protocol->protocol_type_item_id,
            'type_label' => $protocol->type_label,
            'criticality_item_id' => $protocol->criticality_item_id,
            'criticality_label' => $protocol->criticality_label,
            'regulatory_source' => $protocol->regulatory_source,
            'education_scope' => $protocol->education_scope,
            'legal_reference' => $protocol->legal_reference,
            'source_reference' => $protocol->source_reference,
            'required_documents' => $protocol->required_documents,
            'safeguard_measures' => $protocol->safeguard_measures,
            'minimal_actions' => $protocol->minimal_actions,
            'default_due_days' => $protocol->default_due_days,
            'is_sensitive' => (bool) $protocol->is_sensitive,
            'status' => $protocol->status,
            'effective_from' => optional($protocol->effective_from)->toDateString(),
            'effective_to' => optional($protocol->effective_to)->toDateString(),
            'published_at' => optional($protocol->published_at)->toIso8601String(),
            'metadata' => $protocol->metadata,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function validateCompletionRule(
        array $rule,
        ?string $notes,
        ?string $outcome,
        ?string $evidence,
        array $criteria = [],
    ): void {
        $errors = [];
        if (($rule['requires_notes'] ?? false) || ($rule['requires_completion_note'] ?? false)) {
            if (blank($notes)) {
                $errors['notes'] = 'Este paso exige notas de cierre.';
            }
        }
        if (($rule['requires_outcome'] ?? false) && blank($outcome)) {
            $errors['outcome'] = 'Este paso exige registrar un resultado.';
        }
        if (($rule['requires_evidence'] ?? false) && blank($evidence)) {
            $errors['evidence_summary'] = 'Este paso exige registrar evidencia.';
        }
        $criteriaErrors = [];
        $allCriteria = $this->normalizeCriterionDefinitions($rule['all'] ?? []);
        $missingCriteria = collect($allCriteria)
            ->map(fn ($criterion, int $index) => [
                'key' => $this->criterionKey($criterion, 'all', $index),
                'label' => $this->criterionLabel($criterion, 'all', $index),
            ])
            ->reject(fn (array $criterion) => $this->criterionIsConfirmed($criteria, $criterion['key']));
        if ($missingCriteria->isNotEmpty()) {
            $criteriaErrors[] = 'Falta confirmar: '.$missingCriteria->pluck('label')->implode(', ').'.';
        }

        $anyCriteria = $this->normalizeCriterionDefinitions($rule['any'] ?? []);
        $anyDescriptors = collect($anyCriteria)->map(fn ($criterion, int $index) => [
            'key' => $this->criterionKey($criterion, 'any', $index),
            'label' => $this->criterionLabel($criterion, 'any', $index),
        ]);
        if ($anyDescriptors->isNotEmpty()
            && ! $anyDescriptors->contains(fn (array $criterion) => $this->criterionIsConfirmed($criteria, $criterion['key']))) {
            $criteriaErrors[] = 'Debes confirmar al menos un criterio: '.$anyDescriptors->pluck('label')->implode(', ').'.';
        }
        if ($criteriaErrors) {
            $errors['completion_criteria'] = implode(' ', $criteriaErrors);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function criterionKey(mixed $criterion, string $group, int $index): string
    {
        if ((is_string($criterion) || is_int($criterion) || is_float($criterion))
            && trim((string) $criterion) !== '') {
            return trim((string) $criterion);
        }

        if (is_array($criterion)) {
            foreach (['key', 'code', 'field', 'name'] as $candidate) {
                $value = $criterion[$candidate] ?? null;
                if ((is_string($value) || is_int($value) || is_float($value))
                    && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        return "{$group}_".($index + 1);
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeCriterionDefinitions(mixed $definitions): array
    {
        if ($definitions === null || $definitions === '') {
            return [];
        }
        if (! is_array($definitions)) {
            return [$definitions];
        }
        if ($definitions === [] || array_is_list($definitions)) {
            return $definitions;
        }

        // The editor accepts a single criterion object as well as a list.
        return [$definitions];
    }

    private function criterionLabel(mixed $criterion, string $group, int $index): string
    {
        if (is_array($criterion)) {
            foreach (['label', 'title', 'description', 'name'] as $candidate) {
                $value = $criterion[$candidate] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return $this->criterionKey($criterion, $group, $index);
    }

    private function criterionIsConfirmed(array $criteria, string $key): bool
    {
        if (array_key_exists($key, $criteria)) {
            return $this->criterionValueIsConfirmed($criteria[$key]);
        }

        foreach ($criteria as $index => $criterion) {
            if (! is_array($criterion)) {
                continue;
            }
            $submittedKey = $this->criterionKey($criterion, 'submitted', (int) $index);
            if ($submittedKey === $key) {
                return $this->criterionValueIsConfirmed($criterion);
            }
        }

        return false;
    }

    private function criterionValueIsConfirmed(mixed $value): bool
    {
        if (is_array($value)) {
            foreach (['completed', 'confirmed', 'value'] as $candidate) {
                if (array_key_exists($candidate, $value)) {
                    return in_array($value[$candidate], [true, 1, '1'], true);
                }
            }

            return false;
        }

        return in_array($value, [true, 1, '1'], true);
    }

    private function assertExpectedRevision(ConvivenciaProtocol $protocol, ?int $expected): void
    {
        if ($expected !== null && (int) $protocol->revision !== $expected) {
            throw ValidationException::withMessages([
                'expected_revision' => 'El protocolo fue modificado. Recarga antes de guardar.',
            ]);
        }
    }

    private function assertActivationRevision(ConvivenciaProtocolActivation $activation, ?int $expected): void
    {
        if ($expected !== null && (int) $activation->revision !== $expected) {
            throw ValidationException::withMessages([
                'revision' => 'La activación cambió. Recarga antes de continuar.',
            ]);
        }
    }

    private function assertActivationMutable(ConvivenciaProtocolActivation $activation): void
    {
        if (in_array($activation->status, ['cerrado', 'archivado'], true)) {
            throw ValidationException::withMessages([
                'status' => 'La activación está cerrada y conserva su ejecución como registro inmutable.',
            ]);
        }
    }

    private function invalidRequiredParts(Collection $parts, bool $requireAll = false): Collection
    {
        $candidates = $requireAll ? $parts : $parts->where('is_required', true);

        return $candidates->filter(function (ConvivenciaProtocolActivationPart $part) {
            if (! in_array($part->status, ['completed', 'not_applicable'], true)) {
                return true;
            }
            if ($part->status === 'not_applicable') {
                if (! $part->is_required) {
                    if (blank($part->notes)) {
                        return true;
                    }
                }
                $configuration = (array) data_get($part->snapshot, 'configuration', []);
                $condition = (array) data_get($part->snapshot, 'condition', []);

                if ($condition
                    && ! data_get($part->snapshot, 'legacy_materialization', false)
                    && ! ($configuration['blocked_for_preschool_child'] ?? false)
                    && data_get($part->data, 'condition_confirmed') !== false
                    && data_get($part->data, 'condition_not_met_confirmed') !== true) {
                    return true;
                }

                if (! $part->is_required) {
                    return false;
                }

                return blank($part->notes)
                    || (! $condition
                        && ! ($configuration['allow_not_applicable'] ?? false)
                        && ! ($configuration['blocked_for_preschool_child'] ?? false));
            }

            return $part->requires_evidence
                && blank($part->evidence_summary)
                && blank(data_get($part->data, 'evidence'));
        });
    }

    private function dateTimeChanged($current, $requested): bool
    {
        if (! $current && ! $requested) {
            return false;
        }
        if (! $current || ! $requested) {
            return true;
        }

        return ! Carbon::parse($current)->equalTo(Carbon::parse($requested));
    }

    private function uniqueStepCode(ConvivenciaProtocol $protocol, string $name, Collection $saved): string
    {
        $base = str_replace('-', '_', Str::slug($name)) ?: 'paso';
        $candidate = $base;
        $suffix = 2;
        $used = $protocol->steps()->whereNotNull('code')->pluck('code')->merge($saved->pluck('code'));
        while ($used->contains($candidate)) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }

    private function emptyDeadline(): array
    {
        return ['due_at' => null, 'source' => 'not_started', 'fallback_used' => false, 'fallback_reason' => null];
    }

    private function serializableDeadline(array $deadline): array
    {
        return [
            'due_at' => $deadline['due_at']?->toIso8601String(),
            'source' => $deadline['source'],
            'fallback_used' => $deadline['fallback_used'],
            'fallback_reason' => $deadline['fallback_reason'],
        ];
    }
}
