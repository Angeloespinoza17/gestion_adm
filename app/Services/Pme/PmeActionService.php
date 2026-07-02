<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmeActionService
{
    public function __construct(
        private readonly PmeChangeLogService $changeLogService,
    ) {
    }

    public function store(array $payload, User $actor): PmeAction
    {
        $this->validateBusinessRules($payload);

        return DB::transaction(function () use ($payload, $actor) {
            $indicatorIds = $payload['indicator_ids'] ?? [];
            unset($payload['indicator_ids']);

            $action = PmeAction::query()->create(array_merge($payload, [
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));
            $action->indicators()->sync($indicatorIds);

            $this->flagBudgetAlertIfNeeded($action, $actor);
            $this->changeLogService->record($action, 'creada', $actor, null, $action->fresh()->toArray(), 'Acción PME creada.');

            return $this->loadAction($action);
        });
    }

    public function update(PmeAction $action, array $payload, User $actor): PmeAction
    {
        $this->validateBusinessRules($payload, $action);

        return DB::transaction(function () use ($action, $payload, $actor) {
            $before = $action->toArray();
            $indicatorIds = $payload['indicator_ids'] ?? $action->indicators()->pluck('pme_indicadores.id')->all();
            unset($payload['indicator_ids']);

            $action->fill(array_merge($payload, ['updated_by' => $actor->id]));
            $action->save();
            $action->indicators()->sync($indicatorIds);

            $this->flagBudgetAlertIfNeeded($action, $actor);
            $this->changeLogService->record($action, 'actualizada', $actor, $before, $action->fresh()->toArray(), 'Acción PME actualizada.');

            return $this->loadAction($action);
        });
    }

    public function registerProgress(PmeAction $action, array $payload, User $actor): PmeAction
    {
        return DB::transaction(function () use ($action, $payload, $actor) {
            $before = $action->toArray();
            $action->update([
                'progress_percentage' => $payload['progress_percentage'],
                'executed_budget' => $payload['executed_budget'] ?? $action->executed_budget,
                'last_progress_at' => now(),
                'state' => $payload['state'] ?? $this->progressState($payload['progress_percentage'], $action->state),
                'observations' => $payload['notes'] ?? $action->observations,
                'updated_by' => $actor->id,
            ]);

            $this->flagBudgetAlertIfNeeded($action->fresh(), $actor);
            $this->changeLogService->record($action, 'avance_registrado', $actor, $before, $action->fresh()->toArray(), $payload['notes'] ?? 'Se registró avance de la acción.');

            return $this->loadAction($action);
        });
    }

    public function close(PmeAction $action, User $actor, ?string $notes = null): PmeAction
    {
        if ($action->evidences()->count() < max(1, (int) $action->minimum_evidence_required)) {
            throw ValidationException::withMessages([
                'evidences' => 'La acción no puede cerrarse sin la evidencia mínima requerida.',
            ]);
        }

        return DB::transaction(function () use ($action, $actor, $notes) {
            $before = $action->toArray();
            $action->update([
                'state' => 'cerrada',
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'progress_percentage' => max(100, (float) $action->progress_percentage),
                'updated_by' => $actor->id,
                'observations' => $notes ?: $action->observations,
            ]);

            $this->changeLogService->record($action, 'cerrada', $actor, $before, $action->fresh()->toArray(), $notes ?? 'Acción cerrada.');

            return $this->loadAction($action);
        });
    }

    public function reopen(PmeAction $action, User $actor, ?string $notes = null): PmeAction
    {
        return DB::transaction(function () use ($action, $actor, $notes) {
            $before = $action->toArray();
            $action->update([
                'state' => 'en_monitoreo',
                'closed_at' => null,
                'closed_by' => null,
                'updated_by' => $actor->id,
                'observations' => $notes ?: $action->observations,
            ]);

            $this->changeLogService->record($action, 'reabierta', $actor, $before, $action->fresh()->toArray(), $notes ?? 'Acción reabierta.');

            return $this->loadAction($action);
        });
    }

    public function changeState(PmeAction $action, string $state, User $actor, ?string $notes = null): PmeAction
    {
        return DB::transaction(function () use ($action, $state, $actor, $notes) {
            $before = $action->toArray();
            $action->update([
                'state' => $state,
                'updated_by' => $actor->id,
                'observations' => $notes ?: $action->observations,
            ]);

            $this->changeLogService->record($action, 'estado_actualizado', $actor, $before, $action->fresh()->toArray(), $notes ?? "Estado cambiado a {$state}.");

            return $this->loadAction($action);
        });
    }

    private function validateBusinessRules(array $payload, ?PmeAction $action = null): void
    {
        $start = $payload['start_date'] ?? $action?->start_date?->format('Y-m-d');
        $end = $payload['end_date'] ?? $action?->end_date?->format('Y-m-d');
        if ($start && $end && $end < $start) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha de término no puede ser anterior a la fecha de inicio.',
            ]);
        }

        $planned = (float) ($payload['planned_budget'] ?? $action?->planned_budget ?? 0);
        $executed = (float) ($payload['executed_budget'] ?? $action?->executed_budget ?? 0);
        if (($payload['funding_source'] ?? $action?->funding_source) === 'SEP') {
            $indicatorIds = $payload['indicator_ids'] ?? $action?->indicators()->pluck('pme_indicadores.id')->all() ?? [];
            if (empty($payload['pme_objective_id'] ?? $action?->pme_objective_id)
                || empty($payload['pme_strategy_id'] ?? $action?->pme_strategy_id)
                || empty($indicatorIds)) {
                throw ValidationException::withMessages([
                    'indicator_ids' => 'Una acción SEP debe asociarse a objetivo, estrategia e indicador.',
                ]);
            }
        }

        if ($planned < 0 || $executed < 0) {
            throw ValidationException::withMessages([
                'planned_budget' => 'Los montos presupuestarios no pueden ser negativos.',
            ]);
        }
    }

    private function flagBudgetAlertIfNeeded(PmeAction $action, User $actor): void
    {
        if ((float) $action->executed_budget <= (float) $action->planned_budget) {
            return;
        }

        PmeAlert::query()->updateOrCreate(
            [
                'alert_type' => 'presupuesto_superado',
                'related_type' => PmeAction::class,
                'related_id' => $action->id,
            ],
            [
                'pme_plan_id' => $action->pme_plan_id,
                'severity' => 'alta',
                'title' => 'Presupuesto ejecutado sobre lo planificado',
                'message' => "La acción {$action->name} supera el presupuesto planificado.",
                'due_date' => now()->toDateString(),
                'state' => 'pendiente',
                'payload' => [
                    'planned_budget' => $action->planned_budget,
                    'executed_budget' => $action->executed_budget,
                ],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }

    private function progressState(float|int|string $percentage, string $currentState): string
    {
        $value = (float) $percentage;
        if ($value >= 100) {
            return 'finalizada';
        }

        if ($value > 0) {
            return in_array($currentState, ['atrasada', 'suspendida'], true) ? $currentState : 'en_ejecucion';
        }

        return $currentState;
    }

    private function loadAction(PmeAction $action): PmeAction
    {
        return $action->fresh([
            'plan:id,name,school_year',
            'dimension:id,name',
            'objective:id,name',
            'strategy:id,name',
            'responsibleUser:id,name',
            'indicators:id,name',
            'activities',
            'milestones',
            'evidences',
        ]);
    }
}
