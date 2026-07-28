<?php

namespace App\Services\Library;

use App\Models\Library\BibliotecaAlerta;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaPase;
use App\Models\Library\BibliotecaPlanLector;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Library\BibliotecaTextoEntrega;
use App\Models\Library\BibliotecaTextoOrden;
use App\Models\Library\BibliotecaUsoEspacio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BibliotecaDashboardService
{
    public function __construct(
        private readonly BibliotecaLoanService $loanService,
        private readonly BibliotecaAlertService $alertService,
    ) {}

    public function build(): array
    {
        $this->loanService->refreshStatuses();
        $this->alertService->refreshOperationalAlerts();

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $yearStart = $today->copy()->startOfYear();

        $metrics = [
            'total_books' => BibliotecaObra::query()->where('material_type', 'libro')->count(),
            'total_copies_available' => BibliotecaEjemplar::query()->where('availability_status', 'disponible')->where('is_active', true)->count(),
            'copies_loaned' => BibliotecaEjemplar::query()->where('availability_status', 'prestado')->count(),
            'active_loans' => BibliotecaPrestamo::query()->whereIn('status', ['activo', 'renovado', 'vencido'])->count(),
            'pending_returns' => BibliotecaPrestamo::query()->whereIn('status', ['activo', 'renovado', 'vencido'])->count(),
            'students_overdue' => BibliotecaPrestamo::query()->where('status', 'vencido')->whereNotNull('student_profile_id')->distinct('student_profile_id')->count('student_profile_id'),
            'staff_overdue' => BibliotecaPrestamo::query()->where('status', 'vencido')->whereNotNull('staff_id')->distinct('staff_id')->count('staff_id'),
            'most_requested_books' => BibliotecaPrestamo::query()->distinct('biblioteca_obra_id')->count('biblioteca_obra_id'),
            'active_reservations' => BibliotecaReserva::query()->whereIn('status', ['solicitada', 'aprobada'])->count(),
            'reserved_resources' => BibliotecaReserva::query()->whereIn('status', ['solicitada', 'aprobada', 'retirada'])->count(),
            'reserved_spaces' => BibliotecaUsoEspacio::query()->whereIn('status', ['solicitada', 'aprobada'])->count(),
            'reading_plan_activities' => BibliotecaPlanLector::query()->whereIn('status', ['planificado', 'en_ejecucion'])->count(),
            'damaged_books' => BibliotecaEjemplar::query()->where('availability_status', 'danado')->count(),
            'lost_books' => BibliotecaEjemplar::query()->where('availability_status', 'perdido')->count(),
            'new_entries' => BibliotecaEjemplar::query()->whereDate('ingress_date', '>=', $monthStart)->count(),
            'month_loans' => BibliotecaPrestamo::query()->whereDate('borrowed_at', '>=', $monthStart)->count(),
            'month_returns' => BibliotecaPrestamo::query()->whereDate('returned_at', '>=', $monthStart)->count(),
            'overdue_rate' => $this->overdueRate($yearStart, $today->copy()->endOfDay()),
            'pending_textbook_deliveries' => BibliotecaTextoEntrega::query()->whereIn('status', ['pendiente', 'parcial'])->count(),
            'textbook_orders_with_shortages' => BibliotecaTextoOrden::query()->whereHas('items', fn ($query) => $query->where('shortage_quantity', '>', 0))->count(),
            'active_library_passes' => BibliotecaPase::query()->where('status', 'emitido')->where('valid_until', '>=', now())->count(),
        ];

        $alerts = [
            'overdue_loans' => BibliotecaPrestamo::query()->where('status', 'vencido')->count(),
            'pending_reservations' => BibliotecaReserva::query()->where('status', 'solicitada')->count(),
            'upcoming_returns' => BibliotecaPrestamo::query()->whereIn('status', ['activo', 'renovado'])->whereBetween('due_at', [$today, $today->copy()->addDays(3)])->count(),
            'users_with_overdue' => BibliotecaPrestamo::query()
                ->where('status', 'vencido')
                ->get(['student_profile_id', 'staff_id', 'user_id', 'course_section_id'])
                ->map(fn ($loan) => implode(':', [
                    $loan->student_profile_id ?: 0,
                    $loan->staff_id ?: 0,
                    $loan->user_id ?: 0,
                    $loan->course_section_id ?: 0,
                ]))
                ->unique()
                ->count(),
            'resources_reserved_today' => BibliotecaReserva::query()->whereDate('pickup_at', $today)->count(),
            'spaces_reserved_today' => BibliotecaUsoEspacio::query()->whereDate('start_at', $today)->count(),
            'damaged_books' => $metrics['damaged_books'],
            'lost_books' => $metrics['lost_books'],
            'low_availability' => BibliotecaAlerta::query()->where('alert_type', 'baja_disponibilidad')->where('status', 'pendiente')->count(),
            'reading_plan_shortages' => BibliotecaAlerta::query()->where('alert_type', 'plan_lector_faltante')->where('status', 'pendiente')->count(),
        ];

        return [
            'metrics' => $metrics,
            'alerts' => $alerts,
            'charts' => [
                'loans_by_month' => $this->groupByMonth(BibliotecaPrestamo::query(), 'borrowed_at', $yearStart),
                'loans_by_course' => $this->queryCourseTotals(),
                'most_loaned_books' => $this->queryWorkTotals(),
                'categories_usage' => $this->queryCategoryTotals(),
                'subcategories_usage' => $this->querySubcategoryTotals(),
                'overdue_by_course' => $this->queryOverdueByCourse(),
                'space_usage_by_month' => $this->groupByMonth(BibliotecaUsoEspacio::query(), 'start_at', $yearStart),
                'reading_plan_participation' => $this->queryReadingPlanParticipation(),
                'inventory_availability' => $this->inventoryAvailability(),
                'loans_by_estate' => $this->queryLoansByEstate(),
                'inventory_by_location' => $this->queryInventoryByLocation(),
            ],
            'recent' => [
                'loans' => BibliotecaPrestamo::query()->with(['obra:id,title', 'ejemplar:id,code', 'deliveredBy:id,name'])->latest('borrowed_at')->limit(8)->get(),
                'reservations' => BibliotecaReserva::query()->with(['obra:id,title', 'responsible:id,name'])->latest('requested_at')->limit(8)->get(),
                'alerts' => BibliotecaAlerta::query()->latest('created_at')->limit(8)->get(),
            ],
        ];
    }

    private function groupByMonth($query, string $column, Carbon $from): array
    {
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";

        $items = $query
            ->selectRaw("{$monthExpression} as label, count(*) as total")
            ->whereDate($column, '>=', $from)
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return [
            'labels' => $items->pluck('label')->all(),
            'series' => $items->pluck('total')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function queryCourseTotals(): array
    {
        return BibliotecaPrestamo::query()
            ->selectRaw('course_name_snapshot as label, count(*) as total')
            ->whereNotNull('course_name_snapshot')
            ->groupBy('course_name_snapshot')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function queryWorkTotals(): array
    {
        return BibliotecaPrestamo::query()
            ->join('biblioteca_obras', 'biblioteca_obras.id', '=', 'biblioteca_prestamos.biblioteca_obra_id')
            ->selectRaw('biblioteca_obras.title as label, count(*) as total')
            ->groupBy('biblioteca_prestamos.biblioteca_obra_id', 'biblioteca_obras.title')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function queryCategoryTotals(): array
    {
        return BibliotecaPrestamo::query()
            ->join('biblioteca_obras', 'biblioteca_obras.id', '=', 'biblioteca_prestamos.biblioteca_obra_id')
            ->selectRaw("coalesce(biblioteca_obras.category, 'Sin categoría') as label, count(*) as total")
            ->groupBy('biblioteca_obras.category')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function querySubcategoryTotals(): array
    {
        return BibliotecaPrestamo::query()
            ->join('biblioteca_obras', 'biblioteca_obras.id', '=', 'biblioteca_prestamos.biblioteca_obra_id')
            ->leftJoin(
                'biblioteca_categorias',
                'biblioteca_categorias.id',
                '=',
                'biblioteca_obras.biblioteca_categoria_id'
            )
            ->leftJoin(
                'biblioteca_subcategorias',
                'biblioteca_subcategorias.id',
                '=',
                'biblioteca_obras.biblioteca_subcategoria_id'
            )
            ->selectRaw("coalesce(biblioteca_categorias.name, biblioteca_obras.category, 'Sin categoría') as category_label")
            ->selectRaw("coalesce(biblioteca_subcategorias.name, biblioteca_obras.subcategory, 'Sin subcategoría') as label")
            ->selectRaw('count(*) as total')
            ->groupBy(
                'biblioteca_categorias.name',
                'biblioteca_obras.category',
                'biblioteca_subcategorias.name',
                'biblioteca_obras.subcategory'
            )
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'label' => $item->category_label.' · '.$item->label,
                'total' => (int) $item->total,
            ])
            ->all();
    }

    private function queryOverdueByCourse(): array
    {
        return BibliotecaPrestamo::query()
            ->selectRaw("coalesce(course_name_snapshot, 'Sin curso') as label, count(*) as total")
            ->where('status', 'vencido')
            ->groupBy('course_name_snapshot')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function queryReadingPlanParticipation(): array
    {
        return BibliotecaPlanLector::query()
            ->selectRaw('status as label, count(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->toArray();
    }

    private function inventoryAvailability(): array
    {
        return BibliotecaEjemplar::query()
            ->selectRaw('availability_status as label, count(*) as total')
            ->groupBy('availability_status')
            ->orderBy('availability_status')
            ->get()
            ->toArray();
    }

    private function queryLoansByEstate(): array
    {
        return BibliotecaPrestamo::query()
            ->selectRaw("coalesce(borrower_estate, borrower_type, 'sin_estamento') as label, count(*) as total")
            ->where('status', '!=', 'cancelado')
            ->groupBy('borrower_estate', 'borrower_type')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    private function queryInventoryByLocation(): array
    {
        return BibliotecaEjemplar::query()
            ->leftJoin('biblioteca_ubicaciones', 'biblioteca_ubicaciones.id', '=', 'biblioteca_ejemplares.biblioteca_ubicacion_id')
            ->selectRaw("coalesce(biblioteca_ubicaciones.name, biblioteca_ejemplares.physical_location, 'Sin ubicación') as label, count(*) as total")
            ->where('biblioteca_ejemplares.is_active', true)
            ->groupBy('biblioteca_ubicaciones.name', 'biblioteca_ejemplares.physical_location')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->toArray();
    }

    private function overdueRate(Carbon $from, Carbon $to): float
    {
        $dueLoans = BibliotecaPrestamo::query()
            ->whereBetween('due_at', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', 'cancelado')
            ->get(['due_at', 'returned_at', 'status']);

        if ($dueLoans->isEmpty()) {
            return 0.0;
        }

        $late = $dueLoans->filter(fn (BibliotecaPrestamo $loan) => $loan->status === 'vencido'
            || ($loan->returned_at && $loan->returned_at->startOfDay()->greaterThan($loan->due_at->startOfDay())))
            ->count();

        return round(($late / $dueLoans->count()) * 100, 1);
    }
}
