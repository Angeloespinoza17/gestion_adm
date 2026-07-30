<?php

namespace App\Services\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\CentroApuntes\PanolMovimiento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CentroApuntesReportService
{
    private const CLOSED_REQUEST_STATUSES = ['entregada', 'rechazada', 'anulada'];

    private const OUTPUT_MOVEMENT_TYPES = ['salida', 'perdida', 'vencimiento', 'baja'];

    public function build(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveRange($filters);
        [$previousStart, $previousEnd] = $this->previousRange($startDate, $endDate);

        $requests = $this->requestQuery($startDate, $endDate, $filters)
            ->with(['requester:id,name', 'department:id,name', 'subject:id,name', 'machine:id,name'])
            ->orderByDesc('requested_at')
            ->get();
        $previousRequests = $this->requestQuery($previousStart, $previousEnd, $filters)->get();

        $deliveries = $this->deliveryQuery($startDate, $endDate, $filters)
            ->with(['requester:id,name', 'department:id,name', 'details'])
            ->orderByDesc('requested_at')
            ->get();
        $movements = $this->movementQuery($startDate, $endDate, $filters)
            ->with(['insumo:id,name,category,unit_of_measure,current_stock,minimum_stock', 'department:id,name'])
            ->orderByDesc('moved_at')
            ->get();

        $summary = $this->requestSummary($requests);
        $previousSummary = $this->requestSummary($previousRequests);
        $rankings = [
            'users' => $this->groupRequests($requests, fn (CentroApuntesSolicitud $item) => $item->requested_by_name_snapshot ?: 'Sin funcionario'),
            'departments' => $this->groupRequests($requests, fn (CentroApuntesSolicitud $item) => $item->department_name_snapshot ?: 'Sin departamento'),
            'subjects' => $this->groupRequests($requests, fn (CentroApuntesSolicitud $item) => $item->subject_name_snapshot ?: 'Sin asignatura'),
            'machines' => $this->groupRequests($requests, fn (CentroApuntesSolicitud $item) => $item->machine_name_snapshot ?: 'Sin máquina'),
            'task_types' => $this->groupRequests($requests, fn (CentroApuntesSolicitud $item) => $this->humanize($item->task_type === 'otro' ? ($item->task_type_other ?: 'otro') : $item->task_type)),
        ];
        $inventory = $this->inventoryAnalytics($movements, $startDate, $endDate);

        return [
            'generated_at' => now()->toIso8601String(),
            'range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'period' => $filters['period'] ?? 'mensual',
                'days' => $startDate->diffInDays($endDate) + 1,
                'comparison_start' => $previousStart->toDateString(),
                'comparison_end' => $previousEnd->toDateString(),
            ],
            'summary' => [
                ...$summary,
                'deliveries_total' => $deliveries->count(),
                'delivered_materials_total' => round(
                    (float) $deliveries
                        ->flatMap(fn (PanolEntrega $delivery) => $delivery->details)
                        ->sum('quantity'),
                    2
                ),
                ...$inventory['summary'],
            ],
            'comparison' => [
                'summary' => $previousSummary,
                'deltas' => $this->summaryDeltas($summary, $previousSummary),
            ],
            'charts' => [
                'volume_timeline' => $this->volumeTimeline($requests, $startDate, $endDate),
                'service_timeline' => $this->serviceTimeline($requests, $startDate, $endDate),
                'requests_by_status' => $this->requestsByStatus($requests),
                'sheets_by_user' => $rankings['users']->take(12)->values()->all(),
                'sheets_by_department' => $rankings['departments']->take(12)->values()->all(),
                'sheets_by_subject' => $rankings['subjects']->take(12)->values()->all(),
                'sheets_by_machine' => $rankings['machines']->take(12)->values()->all(),
                'sheets_by_task_type' => $rankings['task_types']->take(12)->values()->all(),
                'sheets_by_paper_size' => $this->requestsByPaperSize($requests),
                'supply_consumption' => $inventory['consumption']->take(12)->values()->all(),
                'supply_coverage' => $inventory['coverage']->take(12)->values()->all(),
            ],
            'rankings' => collect($rankings)->map(fn (Collection $items) => $items->values()->all())->all(),
            'sections' => $this->reportSections(
                $requests,
                $summary,
                $previousSummary,
                $rankings,
                $inventory,
            ),
            'metadata' => [
                'costs_included' => false,
                'detail_limit' => 5000,
                'detail_truncated' => $requests->count() > 5000,
            ],
        ];
    }

    private function requestQuery(Carbon $startDate, Carbon $endDate, array $filters): Builder
    {
        $query = CentroApuntesSolicitud::query()
            ->whereBetween('requested_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);

        $this->applyRequestFilters($query, $filters);

        return $query;
    }

    private function deliveryQuery(Carbon $startDate, Carbon $endDate, array $filters): Builder
    {
        return PanolEntrega::query()
            ->whereBetween('requested_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when(! empty($filters['requested_by_user_id']), fn (Builder $builder) => $builder->where('requested_by_user_id', $filters['requested_by_user_id']))
            ->when(! empty($filters['department_id']), fn (Builder $builder) => $builder->where('department_id', $filters['department_id']));
    }

    private function movementQuery(Carbon $startDate, Carbon $endDate, array $filters): Builder
    {
        return PanolMovimiento::query()
            ->whereBetween('moved_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when(! empty($filters['requested_by_user_id']), fn (Builder $builder) => $builder->where('requested_by_user_id', $filters['requested_by_user_id']))
            ->when(! empty($filters['supply_id']), fn (Builder $builder) => $builder->where('insumo_id', $filters['supply_id']))
            ->when(! empty($filters['department_id']), fn (Builder $builder) => $builder->where('department_id', $filters['department_id']))
            ->when(! empty($filters['category']), function (Builder $builder) use ($filters) {
                $builder->whereHas('insumo', fn (Builder $insumoQuery) => $insumoQuery->where('category', $filters['category']));
            });
    }

    private function requestSummary(Collection $requests): array
    {
        $delivered = $requests->filter(fn (CentroApuntesSolicitud $item) => $item->status === 'entregada' && $item->delivered_at);
        $onTime = $delivered->filter(function (CentroApuntesSolicitud $item): bool {
            return Carbon::parse($item->delivered_at)->startOfDay()->lte(Carbon::parse($item->delivery_date)->endOfDay());
        });
        $turnaroundHours = $delivered
            ->map(fn (CentroApuntesSolicitud $item) => round(Carbon::parse($item->requested_at)->diffInMinutes(Carbon::parse($item->delivered_at)) / 60, 2))
            ->sort()
            ->values();
        $urgentTotal = $requests->where('is_urgent', true)->count();
        $closedTotal = $requests->whereIn('status', self::CLOSED_REQUEST_STATUSES)->count();
        $sheetsPrinted = (int) $requests->sum('estimated_total_impressions');

        return [
            'requests_total' => $requests->count(),
            'original_pages_total' => (int) $requests->sum('sheet_count'),
            'copy_sets_total' => (int) $requests->sum('copies_count'),
            'sheets_printed_total' => $sheetsPrinted,
            'delivered_total' => $delivered->count(),
            'pending_total' => $requests->where('status', 'pendiente')->count(),
            'in_progress_total' => $requests->whereIn('status', ['recibida', 'en_proceso', 'pausada'])->count(),
            'ready_total' => $requests->where('status', 'lista_para_retiro')->count(),
            'backlog_total' => $requests->whereNotIn('status', self::CLOSED_REQUEST_STATUSES)->count(),
            'overdue_open_total' => $requests
                ->whereNotIn('status', self::CLOSED_REQUEST_STATUSES)
                ->filter(fn (CentroApuntesSolicitud $item) => Carbon::parse($item->delivery_date)->isBefore(Carbon::today()))
                ->count(),
            'urgent_total' => $urgentTotal,
            'urgent_rate' => $requests->isEmpty() ? 0 : round(($urgentTotal / $requests->count()) * 100, 1),
            'on_time_total' => $onTime->count(),
            'on_time_rate' => $delivered->isEmpty() ? 0 : round(($onTime->count() / $delivered->count()) * 100, 1),
            'completion_rate' => $closedTotal === 0 ? 0 : round(($delivered->count() / $closedTotal) * 100, 1),
            'average_turnaround_hours' => $turnaroundHours->isEmpty() ? 0 : round((float) $turnaroundHours->average(), 1),
            'median_turnaround_hours' => $turnaroundHours->isEmpty() ? 0 : round((float) $turnaroundHours->median(), 1),
            'p90_turnaround_hours' => $this->percentile($turnaroundHours, 90),
            'average_sheets_per_request' => $requests->isEmpty() ? 0 : round($sheetsPrinted / $requests->count(), 1),
        ];
    }

    private function summaryDeltas(array $summary, array $previous): array
    {
        $keys = [
            'requests_total',
            'original_pages_total',
            'sheets_printed_total',
            'delivered_total',
            'backlog_total',
            'overdue_open_total',
            'urgent_rate',
            'on_time_rate',
            'median_turnaround_hours',
        ];

        return collect($keys)->mapWithKeys(function (string $key) use ($summary, $previous): array {
            $currentValue = (float) ($summary[$key] ?? 0);
            $previousValue = (float) ($previous[$key] ?? 0);

            return [$key => $previousValue == 0.0
                ? ($currentValue == 0.0 ? 0.0 : null)
                : round((($currentValue - $previousValue) / abs($previousValue)) * 100, 1)];
        })->all();
    }

    private function groupRequests(Collection $requests, callable $labelResolver): Collection
    {
        $totalSheets = max(1, (int) $requests->sum('estimated_total_impressions'));

        return $requests
            ->groupBy($labelResolver)
            ->map(function (Collection $items, string $label) use ($totalSheets): array {
                $sheetsPrinted = (int) $items->sum('estimated_total_impressions');

                return [
                    'label' => $label,
                    'requests' => $items->count(),
                    'original_pages' => (int) $items->sum('sheet_count'),
                    'copy_sets' => (int) $items->sum('copies_count'),
                    'sheets_printed' => $sheetsPrinted,
                    'total' => $sheetsPrinted,
                    'share' => round(($sheetsPrinted / $totalSheets) * 100, 1),
                    'delivered' => $items->where('status', 'entregada')->count(),
                    'urgent' => $items->where('is_urgent', true)->count(),
                    'departments' => $items->pluck('department_name_snapshot')->filter()->unique()->values()->implode(', '),
                ];
            })
            ->sortByDesc('sheets_printed')
            ->values();
    }

    private function volumeTimeline(Collection $requests, Carbon $startDate, Carbon $endDate): array
    {
        $monthly = $startDate->diffInDays($endDate) > 62;
        $buckets = [];
        $cursor = $monthly ? $startDate->copy()->startOfMonth() : $startDate->copy();
        $last = $monthly ? $endDate->copy()->startOfMonth() : $endDate->copy();

        while ($cursor->lte($last)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $buckets[$key] = [
                'label' => $key,
                'requests' => 0,
                'original_pages' => 0,
                'sheets_printed' => 0,
            ];
            $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ($requests as $request) {
            $key = Carbon::parse($request->requested_at)->format($monthly ? 'Y-m' : 'Y-m-d');
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['requests']++;
            $buckets[$key]['original_pages'] += (int) $request->sheet_count;
            $buckets[$key]['sheets_printed'] += (int) $request->estimated_total_impressions;
        }

        return array_values($buckets);
    }

    private function serviceTimeline(Collection $requests, Carbon $startDate, Carbon $endDate): array
    {
        $monthly = $startDate->diffInDays($endDate) > 62;

        return $requests
            ->filter(fn (CentroApuntesSolicitud $item) => $item->status === 'entregada' && $item->delivered_at)
            ->groupBy(fn (CentroApuntesSolicitud $item) => Carbon::parse($item->requested_at)->format($monthly ? 'Y-m' : 'Y-m-d'))
            ->map(function (Collection $items, string $label): array {
                $onTime = $items->filter(fn (CentroApuntesSolicitud $item) => Carbon::parse($item->delivered_at)->startOfDay()->lte(Carbon::parse($item->delivery_date)->endOfDay()));
                $hours = $items->map(fn (CentroApuntesSolicitud $item) => Carbon::parse($item->requested_at)->diffInMinutes(Carbon::parse($item->delivered_at)) / 60);

                return [
                    'label' => $label,
                    'delivered' => $items->count(),
                    'on_time_rate' => round(($onTime->count() / max(1, $items->count())) * 100, 1),
                    'median_turnaround_hours' => round((float) $hours->median(), 1),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function requestsByStatus(Collection $requests): array
    {
        return $requests
            ->groupBy('status')
            ->map(fn (Collection $items, string $label) => [
                'label' => $this->humanize($label),
                'requests' => $items->count(),
                'sheets_printed' => (int) $items->sum('estimated_total_impressions'),
                'total' => $items->count(),
            ])
            ->sortByDesc('requests')
            ->values()
            ->all();
    }

    private function requestsByPaperSize(Collection $requests): array
    {
        return $requests
            ->groupBy('paper_size')
            ->map(fn (Collection $items, string $label) => [
                'label' => $this->humanize($label),
                'requests' => $items->count(),
                'sheets_printed' => (int) $items->sum('estimated_total_impressions'),
                'total' => (int) $items->sum('estimated_total_impressions'),
            ])
            ->sortByDesc('sheets_printed')
            ->values()
            ->all();
    }

    private function inventoryAnalytics(Collection $movements, Carbon $startDate, Carbon $endDate): array
    {
        $days = max(1, $startDate->diffInDays($endDate) + 1);
        $outputs = $movements->whereIn('movement_type', self::OUTPUT_MOVEMENT_TYPES);
        $consumption = $outputs
            ->groupBy(fn (PanolMovimiento $movement) => $movement->insumo?->name ?: 'Insumo sin identificar')
            ->map(function (Collection $items, string $label) use ($days): array {
                $quantity = round((float) $items->sum('quantity'), 2);
                $supply = $items->first()?->insumo;
                $dailyAverage = round($quantity / $days, 2);
                $coverageDays = $dailyAverage > 0 && $supply
                    ? round((float) $supply->current_stock / $dailyAverage, 1)
                    : null;

                return [
                    'label' => $label,
                    'category' => $this->humanize($supply?->category ?: 'otro'),
                    'unit' => $this->humanize($supply?->unit_of_measure ?: 'unidad'),
                    'quantity' => $quantity,
                    'total' => $quantity,
                    'current_stock' => round((float) ($supply?->current_stock ?? 0), 2),
                    'minimum_stock' => round((float) ($supply?->minimum_stock ?? 0), 2),
                    'daily_average' => $dailyAverage,
                    'coverage_days' => $coverageDays,
                ];
            })
            ->sortByDesc('quantity')
            ->values();

        return [
            'summary' => [
                'supplies_out_total' => round((float) $outputs->sum('quantity'), 2),
                'supplies_in_total' => round((float) $movements->where('movement_type', 'ingreso')->sum('quantity'), 2),
                'supplies_loss_total' => round((float) $movements->whereIn('movement_type', ['perdida', 'vencimiento', 'baja'])->sum('quantity'), 2),
                'critical_stock_total' => PanolInsumo::query()->where('active', true)->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock')->count(),
                'out_of_stock_total' => PanolInsumo::query()->where('active', true)->where('current_stock', '<=', 0)->count(),
                'expiring_soon_total' => PanolInsumo::query()->where('active', true)->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            ],
            'consumption' => $consumption,
            'coverage' => $consumption
                ->filter(fn (array $item) => $item['coverage_days'] !== null)
                ->sortBy('coverage_days')
                ->values(),
        ];
    }

    private function reportSections(
        Collection $requests,
        array $summary,
        array $previousSummary,
        array $rankings,
        array $inventory,
    ): array {
        return [
            [
                'title' => 'Resumen ejecutivo',
                'subtitle' => 'Indicadores operativos sin información de costos.',
                'headers' => ['Indicador', 'Período actual', 'Período anterior', 'Variación'],
                'rows' => [
                    $this->summaryRow('Solicitudes', 'requests_total', $summary, $previousSummary),
                    $this->summaryRow('Páginas originales', 'original_pages_total', $summary, $previousSummary),
                    $this->summaryRow('Hojas impresas', 'sheets_printed_total', $summary, $previousSummary),
                    $this->summaryRow('Solicitudes entregadas', 'delivered_total', $summary, $previousSummary),
                    $this->summaryRow('Cumplimiento de fecha (%)', 'on_time_rate', $summary, $previousSummary),
                    $this->summaryRow('Tiempo mediano de entrega (h)', 'median_turnaround_hours', $summary, $previousSummary),
                    $this->summaryRow('Solicitudes abiertas', 'backlog_total', $summary, $previousSummary),
                    $this->summaryRow('Solicitudes atrasadas', 'overdue_open_total', $summary, $previousSummary),
                ],
            ],
            $this->rankingSection('Hojas impresas por funcionario', $rankings['users'], true),
            $this->rankingSection('Hojas impresas por departamento', $rankings['departments']),
            $this->rankingSection('Hojas impresas por asignatura', $rankings['subjects']),
            $this->rankingSection('Hojas impresas por máquina', $rankings['machines']),
            $this->rankingSection('Hojas impresas por tipo de trabajo', $rankings['task_types']),
            [
                'title' => 'Consumo y cobertura de insumos',
                'headers' => ['Insumo', 'Categoría', 'Consumo', 'Unidad', 'Stock actual', 'Promedio diario', 'Cobertura (días)'],
                'rows' => $inventory['consumption']->map(fn (array $item) => [
                    $item['label'],
                    $item['category'],
                    $item['quantity'],
                    $item['unit'],
                    $item['current_stock'],
                    $item['daily_average'],
                    $item['coverage_days'] ?? 'Sin consumo',
                ])->all(),
            ],
            [
                'title' => 'Detalle de solicitudes de impresión',
                'headers' => ['Fecha', 'Código', 'Funcionario', 'Departamento', 'Asignatura', 'Máquina', 'Páginas', 'Copias', 'Hojas impresas', 'Estado'],
                'rows' => $requests->take(5000)->map(fn (CentroApuntesSolicitud $item) => [
                    optional($item->requested_at)->format('Y-m-d H:i'),
                    $item->request_code,
                    $item->requested_by_name_snapshot,
                    $item->department_name_snapshot ?: 'Sin departamento',
                    $item->subject_name_snapshot,
                    $item->machine_name_snapshot,
                    (int) $item->sheet_count,
                    (int) $item->copies_count,
                    (int) $item->estimated_total_impressions,
                    $this->humanize($item->status),
                ])->all(),
            ],
        ];
    }

    private function rankingSection(string $title, Collection $ranking, bool $includeDepartments = false): array
    {
        $headers = ['Nombre', 'Solicitudes', 'Páginas originales', 'Juegos de copias', 'Hojas impresas', 'Participación'];
        if ($includeDepartments) {
            array_splice($headers, 1, 0, ['Departamento']);
        }

        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => $ranking->map(function (array $item) use ($includeDepartments): array {
                $row = [
                    $item['label'],
                    $item['requests'],
                    $item['original_pages'],
                    $item['copy_sets'],
                    $item['sheets_printed'],
                    $item['share'].'%',
                ];
                if ($includeDepartments) {
                    array_splice($row, 1, 0, [$item['departments'] ?: 'Sin departamento']);
                }

                return $row;
            })->all(),
        ];
    }

    private function summaryRow(string $label, string $key, array $summary, array $previous): array
    {
        $currentValue = (float) ($summary[$key] ?? 0);
        $previousValue = (float) ($previous[$key] ?? 0);
        $delta = $previousValue == 0.0
            ? ($currentValue == 0.0 ? 0.0 : null)
            : round((($currentValue - $previousValue) / abs($previousValue)) * 100, 1);

        return [
            $label,
            $summary[$key] ?? 0,
            $previous[$key] ?? 0,
            $delta === null ? 'Sin base comparable' : (($delta > 0 ? '+' : '').$delta.'%'),
        ];
    }

    private function percentile(Collection $values, int $percentile): float
    {
        if ($values->isEmpty()) {
            return 0;
        }

        $index = max(0, (int) ceil(($percentile / 100) * $values->count()) - 1);

        return round((float) $values->values()->get($index), 1);
    }

    private function previousRange(Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate) + 1;
        $previousEnd = $startDate->copy()->subDay();

        return [$previousEnd->copy()->subDays($days - 1), $previousEnd];
    }

    private function resolveRange(array $filters): array
    {
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            return [
                Carbon::parse($filters['start_date']),
                Carbon::parse($filters['end_date']),
            ];
        }

        $today = Carbon::today();
        $period = $filters['period'] ?? 'mensual';

        return match ($period) {
            'diario' => [$today->copy(), $today->copy()],
            'semanal' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'mensual' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'semestral' => [$today->copy()->subMonths(5)->startOfMonth(), $today->copy()->endOfMonth()],
            'anual' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
    }

    private function applyRequestFilters(Builder $query, array $filters): void
    {
        $query
            ->when(! empty($filters['requested_by_user_id']), fn (Builder $builder) => $builder->where('requested_by_user_id', $filters['requested_by_user_id']))
            ->when(! empty($filters['department_id']), fn (Builder $builder) => $builder->where('department_id', $filters['department_id']))
            ->when(! empty($filters['subject_id']), fn (Builder $builder) => $builder->where('subject_id', $filters['subject_id']))
            ->when(! empty($filters['machine_id']), fn (Builder $builder) => $builder->where('machine_id', $filters['machine_id']))
            ->when(! empty($filters['paper_size']), fn (Builder $builder) => $builder->where('paper_size', $filters['paper_size']))
            ->when(! empty($filters['task_type']), fn (Builder $builder) => $builder->where('task_type', $filters['task_type']))
            ->when(! empty($filters['status']), fn (Builder $builder) => $builder->where('status', $filters['status']))
            ->when(! empty($filters['urgent_only']), fn (Builder $builder) => $builder->where('is_urgent', true))
            ->when(! empty($filters['immediate_only']), fn (Builder $builder) => $builder->where('is_immediate', true));
    }

    private function humanize(?string $value): string
    {
        return str($value ?: '-')->replace('_', ' ')->title()->toString();
    }
}
