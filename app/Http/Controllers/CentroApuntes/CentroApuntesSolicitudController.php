<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroApuntes\ChangeCentroApuntesSolicitudStatusRequest;
use App\Http\Requests\CentroApuntes\RegisterCentroApuntesEntregaRequest;
use App\Http\Requests\CentroApuntes\SaveCentroApuntesSolicitudRequest;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\User;
use App\Services\CentroApuntes\CentroApuntesSolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CentroApuntesSolicitudController extends Controller
{
    public function __construct(
        private readonly CentroApuntesSolicitudService $solicitudService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CentroApuntesSolicitud::class);

        $search = trim((string) $request->query('search'));

        $items = CentroApuntesSolicitud::query()
            ->with(['requester:id,name', 'subject:id,name', 'machine:id,name', 'receivedBy:id,name'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('request_code', 'like', "%{$search}%")
                        ->orWhere('requested_by_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('subject_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('machine_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('task_type_other', 'like', "%{$search}%")
                        ->orWhere('instructions', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('requested_by_user_id'), fn ($builder) => $builder->where('requested_by_user_id', $request->query('requested_by_user_id')))
            ->when($request->filled('subject_id'), fn ($builder) => $builder->where('subject_id', $request->query('subject_id')))
            ->when($request->filled('machine_id'), fn ($builder) => $builder->where('machine_id', $request->query('machine_id')))
            ->when($request->filled('task_type'), fn ($builder) => $builder->where('task_type', $request->query('task_type')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('paper_size'), fn ($builder) => $builder->where('paper_size', $request->query('paper_size')))
            ->when($request->filled('priority'), fn ($builder) => $builder->where('priority', $request->query('priority')))
            ->when($request->boolean('urgent_only'), fn ($builder) => $builder->where('is_urgent', true))
            ->when($request->boolean('immediate_only'), fn ($builder) => $builder->where('is_immediate', true))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('requested_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('requested_at', '<=', $request->query('date_to')))
            ->orderByDesc('requested_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function show(CentroApuntesSolicitud $solicitud): JsonResponse
    {
        $this->authorize('view', $solicitud);

        return response()->json([
            'data' => $solicitud->load([
                'requester:id,name,email,user_type',
                'subject:id,name,code,area,education_level',
                'machine:id,name,internal_code,type,status',
                'receivedBy:id,name,email',
                'attachments.uploadedBy:id,name',
                'history.performedBy:id,name',
            ]),
        ]);
    }

    public function store(SaveCentroApuntesSolicitudRequest $request): JsonResponse
    {
        $this->authorize('create', CentroApuntesSolicitud::class);

        $solicitud = $this->solicitudService->create(
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'message' => 'Solicitud de impresión registrada correctamente.',
            'data' => $solicitud,
        ], 201);
    }

    public function update(SaveCentroApuntesSolicitudRequest $request, CentroApuntesSolicitud $solicitud): JsonResponse
    {
        $this->authorize('update', $solicitud);

        $solicitud = $this->solicitudService->update(
            $solicitud,
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'message' => 'Solicitud actualizada correctamente.',
            'data' => $solicitud,
        ]);
    }

    public function destroy(CentroApuntesSolicitud $solicitud): JsonResponse
    {
        $this->authorize('delete', $solicitud);

        if ($solicitud->status === 'entregada') {
            throw ValidationException::withMessages([
                'solicitud' => 'No se puede eliminar una solicitud que ya fue entregada.',
            ]);
        }

        $this->solicitudService->delete($solicitud);

        return response()->json([
            'message' => 'Solicitud eliminada correctamente.',
        ]);
    }

    public function changeStatus(ChangeCentroApuntesSolicitudStatusRequest $request, CentroApuntesSolicitud $solicitud): JsonResponse
    {
        $this->authorize('changeStatus', $solicitud);

        $solicitud = $this->solicitudService->changeStatus(
            $solicitud,
            $request->user(),
            $request->validated()['status'],
            $request->validated()['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $solicitud,
        ]);
    }

    public function registerDelivery(RegisterCentroApuntesEntregaRequest $request, CentroApuntesSolicitud $solicitud): JsonResponse
    {
        $this->authorize('deliver', $solicitud);

        $receivedBy = User::query()->findOrFail($request->validated()['received_by_user_id']);
        $solicitud = $this->solicitudService->registerDelivery(
            $solicitud,
            $request->user(),
            $receivedBy,
            $request->validated()['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Entrega registrada correctamente.',
            'data' => $solicitud,
        ]);
    }
}
