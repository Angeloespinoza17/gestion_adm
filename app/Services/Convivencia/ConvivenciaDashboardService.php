<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

class ConvivenciaDashboardService
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function build(User $user, array $filters = []): array
    {
        $caseQuery = $this->accessService->applyCaseVisibility(ConvivenciaCase::query(), $user);
        $complaintQuery = $this->accessService->applyComplaintVisibility(ConvivenciaComplaint::query(), $user);
        $derivationQuery = $this->accessService->applyDerivationVisibility(ConvivenciaDerivation::query(), $user);
        $measureQuery = $this->accessService->applyMeasureVisibility(ConvivenciaMeasure::query(), $user);
        $interviewQuery = $this->accessService->applyInterviewVisibility(ConvivenciaInterview::query(), $user);
        $dailyLogQuery = $this->accessService->applyDailyLogVisibility(ConvivenciaDailyLog::query(), $user);
        $protocolQuery = ConvivenciaProtocolActivation::query()
            ->with('protocol:id,name')
            ->when(!$user->isSuperAdmin() && !$this->accessService->canViewSensitiveData($user), function (Builder $query) use ($user) {
                $query->where(function (Builder $inner) use ($user) {
                    $inner
                        ->whereHas('protocol', fn ($sub) => $sub->where('is_sensitive', false))
                        ->orWhere('activated_by', $user->id);
                });
            });

        $this->applyFilters($caseQuery, $filters, 'cases');
        $this->applyFilters($complaintQuery, $filters, 'complaints');
        $this->applyFilters($derivationQuery, $filters, 'derivations');
        $this->applyFilters($measureQuery, $filters, 'measures');
        $this->applyFilters($interviewQuery, $filters, 'interviews');
        $this->applyFilters($dailyLogQuery, $filters, 'daily_logs');

        $monthStart = now()->copy()->subMonths(11)->startOfMonth();
        $months = collect(CarbonPeriod::create($monthStart, '1 month', now()->startOfMonth()))
            ->map(fn ($month) => $month->format('Y-m'));

        $casesByMonth = (clone $caseQuery)
            ->selectRaw("DATE_FORMAT(opened_at, '%Y-%m') as label, COUNT(*) as total")
            ->where('opened_at', '>=', $monthStart)
            ->groupBy('label')
            ->pluck('total', 'label');

        return [
            'metrics' => [
                'open_cases' => (clone $caseQuery)->whereNotIn('status', ['cerrado', 'archivado'])->count(),
                'closed_cases' => (clone $caseQuery)->where('status', 'cerrado')->count(),
                'internal_derivations_pending' => (clone $derivationQuery)->where('scope', 'internal')->whereIn('status', ['ingresada', 'recibida', 'en_revision', 'en_intervencion'])->count(),
                'external_derivations_pending' => (clone $derivationQuery)->where('scope', 'external')->whereIn('status', ['ingresada', 'recibida', 'en_revision', 'en_intervencion'])->count(),
                'pending_measures' => (clone $measureQuery)->whereIn('status', ['asignada', 'en_proceso', 'reprogramada'])->count(),
                'interviews_done' => (clone $interviewQuery)->count(),
                'complaints_received' => (clone $complaintQuery)->count(),
                'active_protocols' => (clone $protocolQuery)->whereIn('status', ['activo', 'en_seguimiento'])->count(),
                'daily_events' => (clone $dailyLogQuery)->count(),
                'overdue_followups' => (clone $caseQuery)
                    ->whereNotIn('status', ['cerrado', 'archivado'])
                    ->whereNotNull('follow_up_due_at')
                    ->where('follow_up_due_at', '<', now())
                    ->count()
                    + (clone $derivationQuery)
                        ->whereIn('status', ['ingresada', 'recibida', 'en_revision', 'en_intervencion'])
                        ->whereNotNull('response_due_at')
                        ->where('response_due_at', '<', now())
                        ->count()
                    + (clone $measureQuery)
                        ->whereIn('status', ['asignada', 'en_proceso', 'reprogramada'])
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now())
                        ->count(),
            ],
            'charts' => [
                'cases_by_classification' => (clone $caseQuery)
                    ->selectRaw('COALESCE(classification_label, "Sin clasificación") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'cases_by_course' => (clone $caseQuery)
                    ->leftJoin('course_sections', 'course_sections.id', '=', 'convivencia_cases.course_section_id')
                    ->selectRaw('COALESCE(course_sections.display_name, "Sin curso") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'cases_by_level' => (clone $caseQuery)
                    ->leftJoin('course_sections', 'course_sections.id', '=', 'convivencia_cases.course_section_id')
                    ->leftJoin('education_levels', 'education_levels.id', '=', 'course_sections.education_level_id')
                    ->selectRaw('COALESCE(education_levels.name, "Sin nivel") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'cases_by_status' => (clone $caseQuery)
                    ->selectRaw('status as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'cases_by_criticality' => (clone $caseQuery)
                    ->selectRaw('COALESCE(criticality_label, "Sin criticidad") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'monthly_trend' => [
                    'labels' => $months->values(),
                    'series' => $months->map(fn ($month) => (int) ($casesByMonth[$month] ?? 0))->values(),
                ],
            ],
            'recent' => [
                'cases' => (clone $caseQuery)->latest('opened_at')->limit(6)->get(['id', 'folio', 'opened_at', 'classification_label', 'criticality_label', 'status']),
                'complaints' => (clone $complaintQuery)->latest('received_at')->limit(6)->get(['id', 'folio', 'received_at', 'complainant_type', 'situation_type_label', 'status']),
                'protocols' => (clone $protocolQuery)->latest('activated_at')->limit(6)->get(['id', 'protocol_id', 'case_id', 'complaint_id', 'activated_at', 'status', 'current_stage_name']),
            ],
        ];
    }

    private function applyFilters(Builder $query, array $filters, string $type): void
    {
        $yearId = $filters['academic_year_id'] ?? null;
        $courseSectionId = $filters['course_section_id'] ?? null;
        $educationLevelId = $filters['education_level_id'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $status = $filters['status'] ?? null;
        $criticality = $filters['criticality_label'] ?? null;
        $classification = $filters['classification_label'] ?? null;

        $query
            ->when($yearId && $query->getModel()->getTable() !== 'convivencia_protocol_activations', fn ($builder) => $builder->where('academic_year_id', $yearId))
            ->when($courseSectionId, fn ($builder) => $builder->where('course_section_id', $courseSectionId))
            ->when($status, fn ($builder) => $builder->where('status', $status))
            ->when($criticality && $query->getModel()->getTable() === 'convivencia_cases', fn ($builder) => $builder->where('criticality_label', $criticality))
            ->when($classification && $query->getModel()->getTable() === 'convivencia_cases', fn ($builder) => $builder->where('classification_label', $classification))
            ->when($educationLevelId, function ($builder) use ($educationLevelId) {
                $builder->whereHas('courseSection', fn ($sub) => $sub->where('education_level_id', $educationLevelId));
            });

        $dateColumn = match ($type) {
            'cases' => 'opened_at',
            'complaints' => 'received_at',
            'derivations' => 'derived_at',
            'measures' => 'assigned_at',
            'interviews' => 'interview_at',
            'daily_logs' => 'happened_at',
            default => null,
        };

        if ($dateColumn) {
            $query
                ->when($from, fn ($builder) => $builder->whereDate($dateColumn, '>=', $from))
                ->when($to, fn ($builder) => $builder->whereDate($dateColumn, '<=', $to));
        }
    }
}
