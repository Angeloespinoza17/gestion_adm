<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Models\Operational\OperationalTransferRequest;
use App\Services\Operational\OperationalTransferAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalTransferReportController extends Controller
{
    public function __construct(private readonly OperationalTransferAccessService $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('exportar_traslados_operativos') || $request->user()->isSuperAdmin(), 403);
        $payload = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'approval_status' => ['nullable', 'string', 'max:50'],
            'service_status' => ['nullable', 'string', 'max:50'],
        ]);
        $query = $this->access->visibleQuery($request->user())
            ->with(['operation.provider:id,name'])
            ->when($payload['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('transport_date', '>=', $date))
            ->when($payload['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('transport_date', '<=', $date))
            ->when($payload['approval_status'] ?? null, fn (Builder $q, string $status) => $q->where('approval_status', $status))
            ->when($payload['service_status'] ?? null, fn (Builder $q, string $status) => $q->where('service_status', $status));

        $rows = (clone $query)->orderByDesc('transport_date')->get();

        return response()->json([
            'summary' => [
                'requests' => $rows->count(),
                'passengers' => $rows->sum('passenger_count'),
                'approved' => $rows->whereIn('approval_status', ['aprobado', 'importado_historico'])->count(),
                'confirmed' => $rows->whereIn('service_status', ['confirmado', 'ejecutado'])->count(),
                'total_cost' => $rows->sum(fn (OperationalTransferRequest $row) => (int) ($row->operation?->final_cost ?? 0)),
                'reduced_mobility' => $rows->where('reduced_mobility', true)->count(),
            ],
            'by_status' => $rows->groupBy('approval_status')->map->count(),
            'by_service' => $rows->groupBy('service_status')->map->count(),
            'rows' => $rows,
        ]);
    }
}
