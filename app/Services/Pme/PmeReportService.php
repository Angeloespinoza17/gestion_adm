<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeEvidence;
use App\Models\Pme\PmeGeneratedReport;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeSepIncome;
use App\Models\Pme\PmeStudentSepClassification;
use App\Models\User;
use Illuminate\Support\Collection;

class PmeReportService
{
    public function generate(array $filters, User $actor): array
    {
        $plan = !empty($filters['pme_plan_id'])
            ? PmePlan::query()->find($filters['pme_plan_id'])
            : PmePlan::query()->where('is_active', true)->first();

        $actions = PmeAction::query()
            ->with(['dimension:id,name', 'objective:id,name', 'strategy:id,name', 'responsibleUser:id,name'])
            ->withCount('evidences')
            ->when($plan, fn ($query) => $query->where('pme_plan_id', $plan->id))
            ->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state', $state))
            ->when($filters['responsible_user_id'] ?? null, fn ($query, $responsible) => $query->where('responsible_user_id', $responsible))
            ->when($filters['pme_dimension_id'] ?? null, fn ($query, $dimension) => $query->where('pme_dimension_id', $dimension))
            ->when($filters['pme_objective_id'] ?? null, fn ($query, $objective) => $query->where('pme_objective_id', $objective))
            ->when($filters['pme_strategy_id'] ?? null, fn ($query, $strategy) => $query->where('pme_strategy_id', $strategy))
            ->get();

        $indicators = PmeIndicator::query()
            ->with(['objective:id,name', 'strategy:id,name', 'responsibleUser:id,name'])
            ->when($plan, fn ($query) => $query->whereHas('objective', fn ($nested) => $nested->where('pme_plan_id', $plan->id)))
            ->get();

        $incomes = PmeSepIncome::query()
            ->when($plan, fn ($query) => $query->where('pme_plan_id', $plan->id))
            ->get();

        $students = PmeStudentSepClassification::query()
            ->with(['student:id,first_name,last_name,registered_name', 'courseSection:id,display_name', 'academicYear:id,year'])
            ->when($filters['academic_year_id'] ?? ($plan?->academic_year_id), fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
            ->get();

        $evidences = PmeEvidence::query()
            ->with(['action:id,name', 'uploadedBy:id,name'])
            ->when($plan, fn ($query) => $query->whereHas('action', fn ($nested) => $nested->where('pme_plan_id', $plan->id)))
            ->get();

        $sections = collect([
            [
                'title' => 'Acciones PME',
                'headers' => ['Acción', 'Dimensión', 'Objetivo', 'Estrategia', 'Responsable', 'Estado', 'Planificado', 'Ejecutado', 'Evidencias'],
                'rows' => $actions->map(fn (PmeAction $action) => [
                    $action->name,
                    $action->dimension?->name,
                    $action->objective?->name,
                    $action->strategy?->name,
                    $action->responsibleUser?->name,
                    $action->state,
                    (float) $action->planned_budget,
                    (float) $action->executed_budget,
                    $action->evidences_count,
                ])->values()->all(),
            ],
            [
                'title' => 'Indicadores',
                'headers' => ['Indicador', 'Objetivo', 'Estrategia', 'Responsable', 'Estado', 'Línea base', 'Meta', 'Actual', 'Cumplimiento %'],
                'rows' => $indicators->map(fn (PmeIndicator $indicator) => [
                    $indicator->name,
                    $indicator->objective?->name,
                    $indicator->strategy?->name,
                    $indicator->responsibleUser?->name,
                    $indicator->state,
                    (float) $indicator->baseline_value,
                    (float) $indicator->target_value,
                    (float) $indicator->current_value,
                    (float) $indicator->compliance_percentage,
                ])->values()->all(),
            ],
            [
                'title' => 'Ingresos SEP',
                'headers' => ['Año', 'Mes', 'Tipo', 'Estimado', 'Recibido', 'Estado'],
                'rows' => $incomes->map(fn (PmeSepIncome $income) => [
                    $income->school_year,
                    $income->month,
                    $income->income_type,
                    (float) $income->estimated_amount,
                    (float) $income->received_amount,
                    $income->state,
                ])->values()->all(),
            ],
            [
                'title' => 'Estudiantes SEP',
                'headers' => ['Estudiante', 'Curso', 'Año', 'Clasificación', 'Estado', 'Fuente'],
                'rows' => $students->map(fn (PmeStudentSepClassification $student) => [
                    $student->student?->registered_name_resolved ?? $student->student?->full_name,
                    $student->courseSection?->display_name,
                    $student->academicYear?->year,
                    $student->classification,
                    $student->state,
                    $student->source,
                ])->values()->all(),
            ],
            [
                'title' => 'Evidencias',
                'headers' => ['Nombre', 'Acción', 'Tipo', 'Estado', 'Subida por', 'Fecha carga'],
                'rows' => $evidences->map(fn (PmeEvidence $evidence) => [
                    $evidence->name,
                    $evidence->action?->name,
                    $evidence->evidence_type,
                    $evidence->review_status,
                    $evidence->uploadedBy?->name,
                    optional($evidence->uploaded_at)->format('Y-m-d H:i'),
                ])->values()->all(),
            ],
        ])->values();

        $report = PmeGeneratedReport::query()->create([
            'pme_plan_id' => $plan?->id,
            'report_type' => $filters['report_type'] ?? 'general',
            'title' => 'Reporte PME / SEP',
            'filters' => $filters,
            'summary' => [
                'actions' => $actions->count(),
                'indicators' => $indicators->count(),
                'incomes' => $incomes->count(),
                'students' => $students->count(),
                'evidences' => $evidences->count(),
            ],
            'format' => $filters['format'] ?? 'pantalla',
            'rows_count' => $sections->sum(fn (array $section) => count($section['rows'])),
            'state' => 'generado',
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);

        return [
            'meta' => [
                'plan' => $plan ? ['id' => $plan->id, 'name' => $plan->name, 'school_year' => $plan->school_year] : null,
                'report_id' => $report->id,
                'generated_at' => $report->generated_at?->toDateTimeString(),
            ],
            'sections' => $sections,
        ];
    }
}
