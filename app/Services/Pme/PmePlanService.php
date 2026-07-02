<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeCycle;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmeObjective;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeStrategy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PmePlanService
{
    public function __construct(
        private readonly PmeChangeLogService $changeLogService,
    ) {
    }

    public function store(array $payload, User $actor): PmePlan
    {
        return DB::transaction(function () use ($payload, $actor) {
            $plan = PmePlan::query()->create(array_merge($payload, [
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));

            $this->syncDefaultCycles($plan, $actor);
            $this->changeLogService->record($plan, 'creado', $actor, null, $plan->toArray(), 'PME creado.');

            return $plan->fresh(['academicYear', 'responsibleUser', 'cycles']);
        });
    }

    public function update(PmePlan $plan, array $payload, User $actor): PmePlan
    {
        return DB::transaction(function () use ($plan, $payload, $actor) {
            $before = $plan->toArray();
            $plan->fill(array_merge($payload, ['updated_by' => $actor->id]));
            $plan->save();

            $this->changeLogService->record($plan, 'actualizado', $actor, $before, $plan->fresh()->toArray(), 'PME actualizado.');

            return $plan->fresh(['academicYear', 'responsibleUser', 'cycles']);
        });
    }

    public function activate(PmePlan $plan, User $actor): PmePlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            PmePlan::query()->where('id', '!=', $plan->id)->update(['is_active' => false, 'updated_by' => $actor->id]);

            $before = $plan->toArray();
            $plan->update([
                'is_active' => true,
                'state' => $plan->state === 'borrador' ? 'en_planificacion' : $plan->state,
                'updated_by' => $actor->id,
            ]);

            $this->changeLogService->record($plan, 'activado', $actor, $before, $plan->fresh()->toArray(), 'PME marcado como vigente.');

            return $plan->fresh(['academicYear', 'responsibleUser', 'cycles']);
        });
    }

    public function close(PmePlan $plan, User $actor, ?string $notes = null): PmePlan
    {
        return DB::transaction(function () use ($plan, $actor, $notes) {
            $before = $plan->toArray();
            $plan->update([
                'state' => 'cerrado',
                'closed_at' => now(),
                'is_active' => false,
                'updated_by' => $actor->id,
                'observations' => $notes ?: $plan->observations,
            ]);

            $plan->cycles()->whereNull('closed_at')->update([
                'state' => 'cerrado',
                'closed_at' => now(),
                'is_current' => false,
                'updated_by' => $actor->id,
            ]);

            $this->changeLogService->record($plan, 'cerrado', $actor, $before, $plan->fresh()->toArray(), $notes ?? 'PME cerrado.');

            return $plan->fresh(['academicYear', 'responsibleUser', 'cycles']);
        });
    }

    public function archive(PmePlan $plan, User $actor, ?string $notes = null): PmePlan
    {
        return DB::transaction(function () use ($plan, $actor, $notes) {
            $before = $plan->toArray();
            $plan->update([
                'state' => 'archivado',
                'archived_at' => now(),
                'is_active' => false,
                'updated_by' => $actor->id,
                'observations' => $notes ?: $plan->observations,
            ]);

            $this->changeLogService->record($plan, 'archivado', $actor, $before, $plan->fresh()->toArray(), $notes ?? 'PME archivado.');

            return $plan->fresh(['academicYear', 'responsibleUser', 'cycles']);
        });
    }

    public function closeCycle(PmeCycle $cycle, User $actor, ?string $notes = null): PmeCycle
    {
        return DB::transaction(function () use ($cycle, $actor, $notes) {
            $before = $cycle->toArray();
            $cycle->update([
                'state' => 'cerrado',
                'closed_at' => now(),
                'is_current' => false,
                'updated_by' => $actor->id,
                'observations' => $notes ?: $cycle->observations,
            ]);

            $next = $cycle->plan?->cycles()->where('sort_order', '>', $cycle->sort_order)->orderBy('sort_order')->first();
            if ($next) {
                $next->update([
                    'state' => 'vigente',
                    'is_current' => true,
                    'updated_by' => $actor->id,
                ]);

                $cycle->plan?->update([
                    'cycle_name' => $next->name,
                    'state' => $next->name === 'monitoreo' ? 'en_monitoreo' : $cycle->plan->state,
                    'updated_by' => $actor->id,
                ]);
            }

            $this->changeLogService->record($cycle, 'ciclo_cerrado', $actor, $before, $cycle->fresh()->toArray(), $notes ?? 'Ciclo PME cerrado.');

            return $cycle->fresh(['plan']);
        });
    }

    public function duplicateStructure(PmePlan $source, array $payload, User $actor): PmePlan
    {
        return DB::transaction(function () use ($source, $payload, $actor) {
            $newPlan = PmePlan::query()->create(array_merge($payload, [
                'cloned_from_plan_id' => $source->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));
            $this->syncDefaultCycles($newPlan, $actor);

            $objectiveMap = [];
            $strategyMap = [];
            $indicatorMap = [];
            $actionMap = [];

            $source->load(['objectives.strategies', 'objectives.indicators', 'actions.indicators', 'actions.activities', 'actions.milestones']);

            foreach ($source->objectives as $objective) {
                $newObjective = PmeObjective::query()->create([
                    'pme_plan_id' => $newPlan->id,
                    'pme_dimension_id' => $objective->pme_dimension_id,
                    'name' => $objective->name,
                    'description' => $objective->description,
                    'strategic_goal' => $objective->strategic_goal,
                    'global_indicator' => $objective->global_indicator,
                    'responsible_user_id' => $objective->responsible_user_id,
                    'start_date' => $newPlan->start_date,
                    'end_date' => $newPlan->end_date,
                    'state' => 'borrador',
                    'progress_percentage' => 0,
                    'observations' => 'Duplicado desde plan anterior.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $objectiveMap[$objective->id] = $newObjective->id;

                foreach ($objective->strategies as $strategy) {
                    $newStrategy = PmeStrategy::query()->create([
                        'pme_objective_id' => $newObjective->id,
                        'name' => $strategy->name,
                        'description' => $strategy->description,
                        'responsible_user_id' => $strategy->responsible_user_id,
                        'execution_period' => $strategy->execution_period,
                        'state' => 'planificada',
                        'progress_percentage' => 0,
                        'observations' => 'Duplicado desde plan anterior.',
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                    $strategyMap[$strategy->id] = $newStrategy->id;
                }

                foreach ($objective->indicators as $indicator) {
                    $newIndicator = PmeIndicator::query()->create([
                        'pme_objective_id' => $newObjective->id,
                        'pme_strategy_id' => $indicator->pme_strategy_id ? ($strategyMap[$indicator->pme_strategy_id] ?? null) : null,
                        'name' => $indicator->name,
                        'description' => $indicator->description,
                        'indicator_type' => $indicator->indicator_type,
                        'baseline_value' => $indicator->baseline_value,
                        'target_value' => $indicator->target_value,
                        'current_value' => null,
                        'measurement_unit' => $indicator->measurement_unit,
                        'verification_source' => $indicator->verification_source,
                        'measurement_frequency' => $indicator->measurement_frequency,
                        'responsible_user_id' => $indicator->responsible_user_id,
                        'state' => 'sin_medicion',
                        'compliance_percentage' => 0,
                        'observations' => 'Duplicado desde plan anterior.',
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                    $indicatorMap[$indicator->id] = $newIndicator->id;
                }
            }

            foreach ($source->actions as $action) {
                $newAction = PmeAction::query()->create([
                    'pme_plan_id' => $newPlan->id,
                    'pme_dimension_id' => $action->pme_dimension_id,
                    'pme_objective_id' => $objectiveMap[$action->pme_objective_id] ?? null,
                    'pme_strategy_id' => $strategyMap[$action->pme_strategy_id] ?? null,
                    'name' => $action->name,
                    'description' => $action->description,
                    'justification' => $action->justification,
                    'responsible_user_id' => $action->responsible_user_id,
                    'responsible_area' => $action->responsible_area,
                    'start_date' => $newPlan->start_date,
                    'end_date' => $newPlan->end_date,
                    'planned_budget' => $action->planned_budget,
                    'committed_budget' => 0,
                    'executed_budget' => 0,
                    'funding_source' => $action->funding_source,
                    'cost_center_reference' => $action->cost_center_reference,
                    'external_accounting_reference' => null,
                    'document_reference' => null,
                    'minimum_evidence_required' => $action->minimum_evidence_required,
                    'progress_percentage' => 0,
                    'state' => 'borrador',
                    'observations' => 'Duplicado desde plan anterior.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $newAction->indicators()->sync(
                    collect($action->indicators)->map(fn ($indicator) => $indicatorMap[$indicator->id] ?? null)->filter()->values()->all()
                );
                $actionMap[$action->id] = $newAction->id;

                foreach ($action->activities as $activity) {
                    $newAction->activities()->create([
                        'name' => $activity->name,
                        'description' => $activity->description,
                        'responsible_user_id' => $activity->responsible_user_id,
                        'scheduled_date' => $newPlan->start_date,
                        'state' => 'pendiente',
                        'observations' => 'Duplicado desde plan anterior.',
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                }

                foreach ($action->milestones as $milestone) {
                    $newAction->milestones()->create([
                        'name' => $milestone->name,
                        'description' => $milestone->description,
                        'planned_date' => $newPlan->start_date,
                        'responsible_user_id' => $milestone->responsible_user_id,
                        'progress_percentage' => 0,
                        'state' => 'pendiente',
                        'observations' => 'Duplicado desde plan anterior.',
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                }
            }

            $this->changeLogService->record($newPlan, 'estructura_duplicada', $actor, null, $newPlan->toArray(), "Estructura duplicada desde el plan {$source->name}.");

            return $newPlan->fresh(['academicYear', 'responsibleUser', 'cycles']);
        });
    }

    private function syncDefaultCycles(PmePlan $plan, User $actor): void
    {
        foreach (PmePlan::CYCLE_OPTIONS as $index => $cycle) {
            PmeCycle::query()->create([
                'pme_plan_id' => $plan->id,
                'name' => $cycle,
                'sort_order' => $index + 1,
                'state' => $index === 0 ? 'vigente' : 'pendiente',
                'is_current' => $index === 0,
                'start_date' => $plan->start_date,
                'end_date' => $plan->end_date,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }
    }
}
