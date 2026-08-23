<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\PreventiveProgramAction;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use Illuminate\Support\Facades\DB;

class RiskMatrixStatisticsService
{
    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $levels = DB::table('prevent_risk_assessments as a')
            ->join('prevent_risk_entries as r', 'r.id', '=', 'a.risk_entry_id')
            ->join('prevent_risk_matrix_tasks as t', 't.id', '=', 'r.risk_matrix_task_id')
            ->join('prevent_risk_matrix_processes as p', 'p.id', '=', 't.risk_matrix_process_id')
            ->join('prevent_risk_matrix_versions as v', 'v.id', '=', 'p.risk_matrix_version_id')
            ->join('prevent_risk_matrices as m', 'm.id', '=', 'v.risk_matrix_id')
            ->leftJoin('prevent_risk_catalog_items as l', 'l.id', '=', 'a.calculated_level_id')
            ->where('a.phase', 'current')->where('a.active', true)
            ->where('m.company_key', config('risk_matrix.company.key'))
            ->whereIn('v.status', ['draft', 'in_review', 'observed', 'approved'])
            ->selectRaw("COALESCE(l.code, 'unassessed') as code, COALESCE(l.name, 'No VEP / sin nivel') as label, COALESCE(l.color, '#667085') as color, COUNT(*) as total")
            ->groupBy('l.code', 'l.name', 'l.color')->orderByDesc('total')->get();

        $heatmap = DB::table('prevent_risk_assessments as a')
            ->join('prevent_risk_entries as r', 'r.id', '=', 'a.risk_entry_id')
            ->join('prevent_risk_matrix_tasks as t', 't.id', '=', 'r.risk_matrix_task_id')
            ->join('prevent_risk_matrix_processes as p', 'p.id', '=', 't.risk_matrix_process_id')
            ->join('prevent_risk_matrix_versions as v', 'v.id', '=', 'p.risk_matrix_version_id')
            ->join('prevent_risk_matrices as m', 'm.id', '=', 'v.risk_matrix_id')
            ->leftJoin('prevent_risk_catalog_items as l', 'l.id', '=', 'a.calculated_level_id')
            ->where('a.method', 'vep')->where('a.phase', 'current')->where('a.active', true)
            ->where('m.company_key', config('risk_matrix.company.key'))
            ->whereIn('v.status', ['draft', 'in_review', 'observed', 'approved'])
            ->selectRaw('a.probability, a.consequence, l.code as level_code, l.name as level_label, l.color, COUNT(*) as total')
            ->groupBy('a.probability', 'a.consequence', 'l.code', 'l.name', 'l.color')->get();

        $actionStats = PreventiveProgramAction::query()
            ->whereHas('program', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return [
            'metrics' => [
                'current_matrices' => RiskMatrix::query()->where('company_key', config('risk_matrix.company.key'))->whereNotNull('active_version_id')->count(),
                'draft_matrices' => RiskMatrixVersion::query()->whereHas('matrix', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))->whereIn('status', ['draft', 'observed'])->count(),
                'pending_approval' => RiskMatrixVersion::query()->whereHas('matrix', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))->where('status', 'in_review')->count(),
                'upcoming_review' => RiskMatrixVersion::query()->whereHas('matrix', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))->where('status', 'approved')->whereBetween('next_review_at', [now(), now()->addDays(30)])->count(),
                'important_risks' => $levels->firstWhere('code', 'important')->total ?? 0,
                'intolerable_risks' => $levels->firstWhere('code', 'intolerable')->total ?? 0,
                'overdue_actions' => PreventiveProgramAction::query()->whereHas('program', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))->whereNotIn('status', ['verified', 'cancelled'])->whereDate('due_date', '<', now())->count(),
                'upcoming_actions' => PreventiveProgramAction::query()->whereHas('program', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))->whereNotIn('status', ['verified', 'cancelled'])->whereBetween('due_date', [now(), now()->addDays(15)])->count(),
                'program_progress' => (int) round((float) DB::table('prevent_preventive_programs')->where('company_key', config('risk_matrix.company.key'))->avg('progress_percentage')),
            ],
            'levels' => $levels,
            'heatmap' => $heatmap,
            'actions_by_status' => $actionStats,
            'risks_by_process' => $this->groupedRisks('p.name'),
            'risks_by_family' => $this->groupedRisks('f.name', true),
        ];
    }

    private function groupedRisks(string $column, bool $family = false)
    {
        $query = DB::table('prevent_risk_entries as r')
            ->join('prevent_risk_matrix_tasks as t', 't.id', '=', 'r.risk_matrix_task_id')
            ->join('prevent_risk_matrix_processes as p', 'p.id', '=', 't.risk_matrix_process_id')
            ->join('prevent_risk_matrix_versions as v', 'v.id', '=', 'p.risk_matrix_version_id')
            ->join('prevent_risk_matrices as m', 'm.id', '=', 'v.risk_matrix_id')
            ->where('m.company_key', config('risk_matrix.company.key'))
            ->whereIn('v.status', ['draft', 'in_review', 'observed', 'approved']);
        if ($family) {
            $query->join('prevent_risk_catalog_items as f', 'f.id', '=', 'r.risk_family_id');
        }

        return $query->selectRaw("{$column} as label, COUNT(*) as total")->groupBy($column)->orderByDesc('total')->limit(12)->get();
    }
}
