<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Porter\StorePorterGoodsMovementRequest;
use App\Http\Requests\Porter\UpdatePorterGoodsMovementStatusRequest;
use App\Models\PorterGoodsMovement;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class PorterGoodsMovementController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
        private readonly PorterAuditService $auditService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $movementType = trim((string) $request->query('movement_type'));

        $query = PorterGoodsMovement::query()
            ->with([
                'department:id,name',
                'responsibleStaff:id,full_name,rut',
                'registeredBy:id,name',
                'deliveredBy:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('goods_detail', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($movementType !== '', fn (Builder $query) => $query->where('movement_type', $movementType))
            ->when($request->query('department_id'), fn (Builder $query, $value) => $query->where('department_id', $value))
            ->when($request->query('date_from'), fn (Builder $query, $value) => $query->whereDate('moved_at', '>=', $value))
            ->when($request->query('date_to'), fn (Builder $query, $value) => $query->whereDate('moved_at', '<=', $value));

        return response()->json(
            $query->latest('moved_at')->latest('id')->paginate((int) $request->query('per_page', 15))
        );
    }

    public function show(Request $request, PorterGoodsMovement $porterGoodsMovement): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $porterGoodsMovement->load([
                'department:id,name',
                'responsibleStaff:id,full_name,rut',
                'registeredBy:id,name,email',
                'deliveredBy:id,name,email',
                'logs.performedBy:id,name,email',
            ]),
        ]);
    }

    public function store(StorePorterGoodsMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('registrar_mercaderia_porteria') || $request->user()?->isSuperAdmin(), 403);

        $payload = $request->validated();
        $attachment = $request->file('attachment');

        $movement = PorterGoodsMovement::create([
            'movement_type' => $payload['movement_type'],
            'department_id' => $payload['department_id'] ?? null,
            'responsible_staff_id' => $payload['responsible_staff_id'] ?? null,
            'registered_by' => $request->user()?->id,
            'status' => $payload['status'] ?? 'recibido_en_porteria',
            'moved_at' => now(),
            'contact_name' => $payload['contact_name'],
            'contact_rut' => $payload['contact_rut'] ?? null,
            'company' => $payload['company'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'vehicle_plate' => $payload['vehicle_plate'] ?? null,
            'goods_detail' => $payload['goods_detail'],
            'quantity' => $payload['quantity'] ?? null,
            'unit' => $payload['unit'] ?? null,
            'document_type' => $payload['document_type'] ?? null,
            'document_number' => $payload['document_number'] ?? null,
            'observations' => $payload['observations'] ?? null,
        ]);

        if ($attachment instanceof UploadedFile) {
            $this->storeAttachment($movement, $attachment);
        }

        $this->auditService->log(
            $movement,
            'registro_mercaderia',
            null,
            $movement->status,
            'Movimiento de mercadería registrado.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Movimiento de mercadería registrado correctamente.',
            'data' => $movement->fresh()->load([
                'department:id,name',
                'responsibleStaff:id,full_name,rut',
                'registeredBy:id,name',
            ]),
        ], 201);
    }

    public function updateStatus(UpdatePorterGoodsMovementStatusRequest $request, PorterGoodsMovement $porterGoodsMovement): JsonResponse
    {
        $canDeliver = $request->user()?->hasPermission('entregar_mercaderia_porteria') || $request->user()?->isSuperAdmin();
        $canRegister = $request->user()?->hasPermission('registrar_mercaderia_porteria') || $request->user()?->isSuperAdmin();
        abort_unless($canDeliver || $canRegister, 403);

        $payload = $request->validated();
        $fromStatus = $porterGoodsMovement->status;

        $updates = [
            'status' => $payload['status'],
            'delivery_observations' => $payload['delivery_observations'] ?? $porterGoodsMovement->delivery_observations,
        ];

        if ($payload['status'] === 'entregado_a_responsable') {
            $updates['delivered_at'] = now();
            $updates['delivered_by'] = $request->user()?->id;
            $updates['received_by_name'] = $payload['received_by_name'] ?? $porterGoodsMovement->received_by_name;
            $updates['received_by_identifier'] = $payload['received_by_identifier'] ?? $porterGoodsMovement->received_by_identifier;
        }

        $porterGoodsMovement->update($updates);

        $this->auditService->log(
            $porterGoodsMovement->fresh(),
            'cambio_estado_mercaderia',
            $fromStatus,
            $payload['status'],
            $payload['delivery_observations'] ?? 'Estado actualizado en portería.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $porterGoodsMovement->fresh()->load([
                'department:id,name',
                'responsibleStaff:id,full_name,rut',
                'registeredBy:id,name',
                'deliveredBy:id,name',
            ]),
        ]);
    }

    private function storeAttachment(PorterGoodsMovement $movement, UploadedFile $attachment): void
    {
        $path = $attachment->storePubliclyAs(
            sprintf('porter/goods-movements/%d', $movement->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $attachment->getClientOriginalName(),
            ['disk' => 'public']
        );

        $movement->update([
            'attachment_path' => $path,
            'attachment_original_name' => $attachment->getClientOriginalName(),
            'attachment_mime_type' => $attachment->getClientMimeType(),
        ]);
    }
}
