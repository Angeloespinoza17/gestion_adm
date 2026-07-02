<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroApuntes\SavePanolEntregaRequest;
use App\Http\Requests\CentroApuntes\UpdatePanolEntregaStatusRequest;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\User;
use App\Services\CentroApuntes\PanolDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanolEntregaController extends Controller
{
    public function __construct(
        private readonly PanolDeliveryService $deliveryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PanolEntrega::class);

        $search = trim((string) $request->query('search'));
        $items = PanolEntrega::query()
            ->with(['requester:id,name', 'withdrawnBy:id,name', 'department:id,name', 'approvedBy:id,name', 'deliveredBy:id,name'])
            ->withCount('details')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('delivery_code', 'like', "%{$search}%")
                        ->orWhere('requested_by_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('department_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('requested_by_user_id'), fn ($builder) => $builder->where('requested_by_user_id', $request->query('requested_by_user_id')))
            ->when($request->filled('department_id'), fn ($builder) => $builder->where('department_id', $request->query('department_id')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->latest('requested_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function show(PanolEntrega $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        return response()->json([
            'data' => $delivery->load([
                'requester:id,name,email',
                'withdrawnBy:id,name,email',
                'department:id,name',
                'approvedBy:id,name',
                'deliveredBy:id,name',
                'details.insumo:id,name,category,unit_of_measure,current_stock,status',
            ]),
        ]);
    }

    public function store(SavePanolEntregaRequest $request): JsonResponse
    {
        $this->authorize('create', PanolEntrega::class);

        $delivery = $this->deliveryService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Solicitud de materiales registrada correctamente.',
            'data' => $delivery,
        ], 201);
    }

    public function update(SavePanolEntregaRequest $request, PanolEntrega $delivery): JsonResponse
    {
        $this->authorize('update', $delivery);

        $delivery = $this->deliveryService->update($delivery, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Solicitud de materiales actualizada correctamente.',
            'data' => $delivery,
        ]);
    }

    public function approve(UpdatePanolEntregaStatusRequest $request, PanolEntrega $delivery): JsonResponse
    {
        $this->authorize('approve', $delivery);

        $delivery = $this->deliveryService->approve($delivery, $request->user(), $request->validated()['notes'] ?? null);

        return response()->json([
            'message' => 'Solicitud aprobada correctamente.',
            'data' => $delivery,
        ]);
    }

    public function reject(UpdatePanolEntregaStatusRequest $request, PanolEntrega $delivery): JsonResponse
    {
        $this->authorize('approve', $delivery);

        $delivery = $this->deliveryService->reject($delivery, $request->user(), $request->validated()['notes'] ?? null);

        return response()->json([
            'message' => 'Solicitud rechazada correctamente.',
            'data' => $delivery,
        ]);
    }

    public function annul(UpdatePanolEntregaStatusRequest $request, PanolEntrega $delivery): JsonResponse
    {
        $this->authorize('approve', $delivery);

        $delivery = $this->deliveryService->annul($delivery, $request->user(), $request->validated()['notes'] ?? null);

        return response()->json([
            'message' => 'Solicitud anulada correctamente.',
            'data' => $delivery,
        ]);
    }

    public function deliver(UpdatePanolEntregaStatusRequest $request, PanolEntrega $delivery): JsonResponse
    {
        $this->authorize('deliver', $delivery);

        $withdrawnBy = !empty($request->validated()['withdrawn_by_user_id'])
            ? User::query()->findOrFail($request->validated()['withdrawn_by_user_id'])
            : null;

        $delivery = $this->deliveryService->deliver(
            $delivery,
            $request->user(),
            $withdrawnBy,
            $request->validated()['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Entrega registrada correctamente.',
            'data' => $delivery,
        ]);
    }

    public function destroy(PanolEntrega $delivery): JsonResponse
    {
        $this->authorize('delete', $delivery);

        $this->deliveryService->delete($delivery);

        return response()->json([
            'message' => 'Solicitud de materiales eliminada correctamente.',
        ]);
    }
}
