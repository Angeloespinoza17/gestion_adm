<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaAlerta;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaPase;
use App\Models\Library\BibliotecaPlanLector;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Library\BibliotecaTextoEntrega;
use App\Models\Library\BibliotecaTextoOrden;
use App\Models\Library\BibliotecaUsoEspacio;
use Carbon\Carbon;

class BibliotecaReportService
{
    public function generate(array $filters): array
    {
        [$from, $to] = $this->resolveRange($filters);

        $loans = BibliotecaPrestamo::query()
            ->with(['obra:id,title,category,genre', 'student:id,first_name,last_name', 'staff:id,full_name', 'courseSection:id,display_name'])
            ->whereBetween('borrowed_at', [$from, $to])
            ->get();

        $reservations = BibliotecaReserva::query()
            ->with(['obra:id,title,category', 'student:id,first_name,last_name', 'staff:id,full_name', 'courseSection:id,display_name'])
            ->whereBetween('requested_at', [$from, $to])
            ->get();

        $spaceUsage = BibliotecaUsoEspacio::query()
            ->with(['espacio:id,name', 'courseSection:id,display_name', 'responsibleStaff:id,full_name'])
            ->whereBetween('start_at', [$from, $to])
            ->get();

        $readingPlans = BibliotecaPlanLector::query()
            ->with(['obra:id,title,category', 'courseSection:id,display_name', 'responsibleStaff:id,full_name'])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('end_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->get();

        $dueLoans = BibliotecaPrestamo::query()
            ->whereBetween('due_at', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', 'cancelado')
            ->get(['due_at', 'returned_at', 'status']);
        $lateLoans = $dueLoans->filter(fn (BibliotecaPrestamo $loan) => $loan->status === 'vencido'
            || ($loan->returned_at && $loan->returned_at->startOfDay()->greaterThan($loan->due_at->startOfDay())))
            ->count();
        $overdueRate = $dueLoans->isEmpty() ? 0 : round(($lateLoans / $dueLoans->count()) * 100, 1);

        return [
            'summary' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'total_loans' => $loans->count(),
                'total_returns' => $loans->whereNotNull('returned_at')->count(),
                'total_overdue' => $loans->where('status', 'vencido')->count(),
                'overdue_rate' => $overdueRate,
                'total_reservations' => $reservations->count(),
                'total_spaces' => $spaceUsage->count(),
                'total_reading_plans' => $readingPlans->count(),
                'pending_textbook_deliveries' => BibliotecaTextoEntrega::query()->whereIn('status', ['pendiente', 'parcial'])->count(),
                'textbook_orders_with_shortages' => BibliotecaTextoOrden::query()->whereHas('items', fn ($query) => $query->where('shortage_quantity', '>', 0))->count(),
                'active_library_passes' => BibliotecaPase::query()->where('status', 'emitido')->where('valid_until', '>=', now())->count(),
                'inventory_status' => BibliotecaEjemplar::query()
                    ->selectRaw('availability_status as label, count(*) as total')
                    ->groupBy('availability_status')
                    ->orderBy('availability_status')
                    ->get(),
            ],
            'sections' => [
                'loans_by_course' => $loans->groupBy('course_name_snapshot')->map->count()->sortDesc()->take(15),
                'loans_by_book' => $loans->groupBy(fn ($loan) => $loan->obra?->title ?? 'Sin título')->map->count()->sortDesc()->take(15),
                'loans_by_category' => $loans->groupBy(fn ($loan) => $loan->obra?->category ?? 'Sin categoría')->map->count()->sortDesc()->take(15),
                'loans_by_estate' => $loans->groupBy(fn ($loan) => $loan->borrower_estate ?: $loan->borrower_type ?: 'Sin estamento')->map->count()->sortDesc(),
                'overdue_by_user' => $loans->where('status', 'vencido')->groupBy('borrower_name_snapshot')->map->count()->sortDesc()->take(15),
                'inventory_by_location' => BibliotecaEjemplar::query()
                    ->leftJoin('biblioteca_ubicaciones', 'biblioteca_ubicaciones.id', '=', 'biblioteca_ejemplares.biblioteca_ubicacion_id')
                    ->selectRaw("coalesce(biblioteca_ubicaciones.name, biblioteca_ejemplares.physical_location, 'Sin ubicación') as label, count(*) as total")
                    ->groupBy('biblioteca_ubicaciones.name', 'biblioteca_ejemplares.physical_location')
                    ->orderByDesc('total')
                    ->get()
                    ->pluck('total', 'label'),
                'reservations_by_type' => $reservations->groupBy('resource_type')->map->count()->sortDesc(),
                'reading_plan_by_status' => $readingPlans->groupBy('status')->map->count()->sortDesc(),
                'spaces_by_activity' => $spaceUsage->groupBy('activity_type')->map->count()->sortDesc(),
                'current_alerts' => BibliotecaAlerta::query()->where('status', 'pendiente')->select('title', 'alert_level', 'alert_type', 'created_at')->limit(20)->get(),
            ],
            'detail' => [
                'loans' => $loans,
                'reservations' => $reservations,
                'reading_plans' => $readingPlans,
                'space_usage' => $spaceUsage,
            ],
        ];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRange(array $filters): array
    {
        $period = $filters['period'] ?? 'monthly';
        $from = ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $to = ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null;

        if ($from && $to) {
            return [$from, $to];
        }

        $today = Carbon::today();

        return match ($period) {
            'daily' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'weekly' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'semestral' => [$today->copy()->subMonths(6)->startOfDay(), $today->copy()->endOfDay()],
            'annual' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
    }
}
