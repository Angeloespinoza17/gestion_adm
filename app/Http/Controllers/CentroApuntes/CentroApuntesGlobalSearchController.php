<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolInsumo;
use App\Services\CentroApuntes\CentroApuntesAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentroApuntesGlobalSearchController extends Controller
{
    public function __construct(
        private readonly CentroApuntesAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = trim((string) $request->query('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $results = collect();

        CentroApuntesSolicitud::query()
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('request_code', 'like', "%{$query}%")
                    ->orWhere('requested_by_name_snapshot', 'like', "%{$query}%")
                    ->orWhere('subject_name_snapshot', 'like', "%{$query}%")
                    ->orWhere('machine_name_snapshot', 'like', "%{$query}%")
                    ->orWhere('task_type', 'like', "%{$query}%")
                    ->orWhere('task_type_other', 'like', "%{$query}%");
            })
            ->latest('requested_at')
            ->limit(8)
            ->get()
            ->each(fn (CentroApuntesSolicitud $item) => $results->push([
                'type' => 'solicitud',
                'id' => $item->id,
                'label' => $item->request_code . ' · ' . $item->requested_by_name_snapshot,
                'subtitle' => sprintf('%s · %s · %s', $item->subject_name_snapshot, str($item->status)->replace('_', ' ')->title(), $item->machine_name_snapshot),
                'route' => '/centro-apuntes/solicitudes',
                'query' => ['solicitud' => $item->id],
            ]));

        PanolInsumo::query()
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%")
                    ->orWhere('status', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->each(fn (PanolInsumo $item) => $results->push([
                'type' => 'insumo',
                'id' => $item->id,
                'label' => $item->name,
                'subtitle' => sprintf('%s · stock %s %s · %s', str($item->category)->replace('_', ' ')->title(), $item->current_stock, $item->unit_of_measure, str($item->status)->replace('_', ' ')->title()),
                'route' => '/centro-apuntes/insumos',
                'query' => ['supply' => $item->id],
            ]));

        PanolEntrega::query()
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('delivery_code', 'like', "%{$query}%")
                    ->orWhere('requested_by_name_snapshot', 'like', "%{$query}%")
                    ->orWhere('department_name_snapshot', 'like', "%{$query}%")
                    ->orWhere('status', 'like', "%{$query}%");
            })
            ->latest('requested_at')
            ->limit(6)
            ->get()
            ->each(fn (PanolEntrega $item) => $results->push([
                'type' => 'entrega',
                'id' => $item->id,
                'label' => $item->delivery_code . ' · ' . $item->requested_by_name_snapshot,
                'subtitle' => sprintf('%s · %s', $item->department_name_snapshot ?: 'Sin área', str($item->status)->replace('_', ' ')->title()),
                'route' => '/centro-apuntes/entregas',
                'query' => ['delivery' => $item->id],
            ]));

        return response()->json([
            'data' => $results->take(20)->values()->all(),
        ]);
    }
}
