<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmeMilestone;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeReflectiveMonitoring;
use App\Models\Pme\PmeSepIncome;
use App\Models\Pme\PmeStrategicGoalMeasurement;
use App\Models\Pme\PmeStudentSepClassification;
use Illuminate\Support\Collection;

class PmeAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function build(?PmePlan $plan = null): Collection
    {
        $plan ??= PmePlan::query()->where('is_active', true)->first();
        if (!$plan) {
            return collect();
        }

        $actions = PmeAction::query()
            ->withCount('evidences')
            ->where('pme_plan_id', $plan->id)
            ->get(['id', 'name', 'state', 'end_date', 'planned_budget', 'executed_budget']);
        $indicators = PmeIndicator::query()
            ->whereHas('objective', fn ($query) => $query->where('pme_plan_id', $plan->id))
            ->get(['id', 'name', 'state', 'target_value', 'current_value']);
        $milestones = PmeMilestone::query()
            ->whereHas('action', fn ($query) => $query->where('pme_plan_id', $plan->id))
            ->get(['id', 'name', 'state', 'planned_date']);
        $goalMeasurements = PmeStrategicGoalMeasurement::query()
            ->whereHas('objective', fn ($query) => $query->where('pme_plan_id', $plan->id))
            ->get(['id', 'goal_label', 'state']);
        $monitorings = PmeReflectiveMonitoring::query()
            ->where('pme_plan_id', $plan->id)
            ->get(['id', 'state', 'monitored_at']);
        $incomes = PmeSepIncome::query()
            ->where('pme_plan_id', $plan->id)
            ->get(['id', 'supporting_document_path', 'state']);
        $studentClassifications = PmeStudentSepClassification::query()
            ->where('academic_year_id', $plan->academic_year_id)
            ->get(['id', 'classification', 'state']);

        return collect([
            [
                'type' => 'acciones_atrasadas',
                'severity' => 'alta',
                'count' => $actions->filter(fn (PmeAction $action) => $action->state === 'atrasada' || ($action->end_date && $action->end_date->isPast() && !in_array($action->state, ['finalizada', 'cerrada', 'anulada'], true)))->count(),
                'title' => 'Acciones atrasadas',
                'message' => 'Acciones con fecha vencida o marcadas como atrasadas.',
            ],
            [
                'type' => 'acciones_sin_evidencia',
                'severity' => 'media',
                'count' => $actions->filter(fn (PmeAction $action) => $action->evidences_count === 0 && in_array($action->state, ['aprobada', 'en_ejecucion', 'en_monitoreo'], true))->count(),
                'title' => 'Acciones sin evidencia',
                'message' => 'Acciones en desarrollo sin respaldos adjuntos.',
            ],
            [
                'type' => 'evidencias_pendientes',
                'severity' => 'media',
                'count' => $plan->actions()->withCount(['evidences' => fn ($query) => $query->whereIn('review_status', ['cargada', 'en_revision'])])->get()->sum('evidences_count'),
                'title' => 'Evidencias pendientes de revisión',
                'message' => 'Respaldo cargado que aún espera revisión.',
            ],
            [
                'type' => 'indicadores_criticos',
                'severity' => 'alta',
                'count' => $indicators->filter(fn (PmeIndicator $indicator) => $indicator->state === 'critico')->count(),
                'title' => 'Indicadores críticos',
                'message' => 'Indicadores por debajo del desempeño esperado.',
            ],
            [
                'type' => 'hitos_vencidos',
                'severity' => 'alta',
                'count' => $milestones->filter(fn (PmeMilestone $milestone) => $milestone->state === 'atrasado' || ($milestone->planned_date && $milestone->planned_date->isPast() && $milestone->state !== 'cumplido'))->count(),
                'title' => 'Hitos vencidos',
                'message' => 'Hitos pendientes con fecha comprometida vencida.',
            ],
            [
                'type' => 'metas_sin_medicion',
                'severity' => 'media',
                'count' => $goalMeasurements->filter(fn (PmeStrategicGoalMeasurement $measurement) => $measurement->state === 'sin_medicion')->count(),
                'title' => 'Metas sin medición',
                'message' => 'Metas estratégicas aún no medidas en el período.',
            ],
            [
                'type' => 'monitoreos_pendientes',
                'severity' => 'media',
                'count' => $monitorings->filter(fn (PmeReflectiveMonitoring $monitoring) => in_array($monitoring->state, ['borrador', 'con_ajustes_pendientes'], true))->count(),
                'title' => 'Monitoreos pendientes',
                'message' => 'Monitoreos reflexivos pendientes de cierre o ajuste.',
            ],
            [
                'type' => 'presupuesto_superado',
                'severity' => 'alta',
                'count' => $actions->filter(fn (PmeAction $action) => (float) $action->executed_budget > (float) $action->planned_budget)->count(),
                'title' => 'Presupuesto ejecutado superior al planificado',
                'message' => 'Acciones con gasto ejecutado superior al presupuesto original.',
            ],
            [
                'type' => 'ingresos_sin_respaldo',
                'severity' => 'media',
                'count' => $incomes->filter(fn (PmeSepIncome $income) => empty($income->supporting_document_path))->count(),
                'title' => 'Ingresos SEP sin respaldo',
                'message' => 'Ingresos sin comprobante adjunto.',
            ],
            [
                'type' => 'estudiantes_sin_clasificacion',
                'severity' => 'media',
                'count' => $studentClassifications->filter(fn (PmeStudentSepClassification $item) => $item->classification === 'pendiente_validacion')->count(),
                'title' => 'Estudiantes sin clasificación SEP',
                'message' => 'Casos pendientes de validación de condición SEP.',
            ],
            [
                'type' => 'acciones_proximas_vencer',
                'severity' => 'baja',
                'count' => $actions->filter(fn (PmeAction $action) => $action->end_date && $action->end_date->between(now(), now()->copy()->addDays(10)) && !in_array($action->state, ['finalizada', 'cerrada', 'anulada'], true))->count(),
                'title' => 'Acciones próximas a vencer',
                'message' => 'Acciones que vencen en los próximos 10 días.',
            ],
        ])->filter(fn (array $alert) => ($alert['count'] ?? 0) > 0)->values();
    }
}
