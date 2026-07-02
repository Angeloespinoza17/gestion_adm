<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Porter\StorePorterReceivedItemRequest;
use App\Http\Requests\Porter\UpdatePorterReceivedItemStatusRequest;
use App\Models\PorterReceivedItem;
use App\Models\StudentProfile;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterAuditService;
use App\Services\Porter\PorterStudentContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PorterReceivedItemController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
        private readonly PorterAuditService $auditService,
        private readonly PorterStudentContextService $studentContextService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $recipientType = trim((string) $request->query('recipient_type'));
        $itemType = trim((string) $request->query('item_type'));

        $query = PorterReceivedItem::query()
            ->with([
                'studentProfile:id,first_name,last_name,rut',
                'staff:id,full_name,rut',
                'department:id,name',
                'registeredBy:id,name',
                'deliveredBy:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('received_from_name', 'like', "%{$search}%")
                        ->orWhere('recipient_label', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($recipientType !== '', fn (Builder $query) => $query->where('recipient_type', $recipientType))
            ->when($itemType !== '', fn (Builder $query) => $query->where('item_type', $itemType))
            ->when($request->query('date_from'), fn (Builder $query, $value) => $query->whereDate('received_at', '>=', $value))
            ->when($request->query('date_to'), fn (Builder $query, $value) => $query->whereDate('received_at', '<=', $value));

        return response()->json(
            $query->latest('received_at')->latest('id')->paginate((int) $request->query('per_page', 15))
        );
    }

    public function show(Request $request, PorterReceivedItem $porterReceivedItem): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $porterReceivedItem->load([
                'studentProfile:id,first_name,last_name,rut',
                'staff:id,full_name,rut',
                'department:id,name',
                'registeredBy:id,name,email',
                'deliveredBy:id,name,email',
                'logs.performedBy:id,name,email',
            ]),
        ]);
    }

    public function store(StorePorterReceivedItemRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('registrar_objetos_porteria') || $request->user()?->isSuperAdmin(), 403);

        $payload = $request->validated();
        $activeYear = $this->studentContextService->activeAcademicYear();
        $student = !empty($payload['student_profile_id']) ? StudentProfile::query()->find($payload['student_profile_id']) : null;
        $currentEnrollment = $student ? $this->studentContextService->currentEnrollment($student, $activeYear) : null;

        $recipientCheck = $this->validateRecipientPayload($payload);
        if ($recipientCheck) {
            return $recipientCheck;
        }

        $item = DB::transaction(function () use ($request, $payload, $student, $currentEnrollment, $activeYear) {
            $attachment = $request->file('attachment');

            $item = PorterReceivedItem::create([
                'recipient_type' => $payload['recipient_type'],
                'recipient_label' => $this->resolveRecipientLabel($payload, $student),
                'student_profile_id' => $payload['student_profile_id'] ?? null,
                'staff_id' => $payload['staff_id'] ?? null,
                'department_id' => $payload['department_id'] ?? null,
                'academic_year_id' => $currentEnrollment?->academic_year_id ?? $activeYear?->id,
                'course_section_id' => $currentEnrollment?->course_section_id,
                'registered_by' => $request->user()?->id,
                'status' => $payload['status'] ?? 'recibido_en_porteria',
                'received_at' => now(),
                'received_from_name' => $payload['received_from_name'],
                'received_from_rut' => $payload['received_from_rut'] ?? null,
                'received_from_phone' => $payload['received_from_phone'] ?? null,
                'item_type' => $payload['item_type'],
                'description' => $payload['description'],
                'observations' => $payload['observations'] ?? null,
            ]);

            if ($attachment instanceof UploadedFile) {
                $this->storeAttachment($item, $attachment);
            }

            return $item;
        });

        $this->auditService->log(
            $item,
            'registro_recepcion',
            null,
            $item->status,
            'Recepción registrada en portería.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Recepción registrada correctamente.',
            'data' => $item->fresh()->load([
                'studentProfile:id,first_name,last_name,rut',
                'staff:id,full_name,rut',
                'department:id,name',
                'registeredBy:id,name',
            ]),
        ], 201);
    }

    public function updateStatus(UpdatePorterReceivedItemStatusRequest $request, PorterReceivedItem $porterReceivedItem): JsonResponse
    {
        $canDeliver = $request->user()?->hasPermission('entregar_objetos_porteria') || $request->user()?->isSuperAdmin();
        $canRegister = $request->user()?->hasPermission('registrar_objetos_porteria') || $request->user()?->isSuperAdmin();
        abort_unless($canDeliver || $canRegister, 403);

        $payload = $request->validated();
        $fromStatus = $porterReceivedItem->status;

        $updates = [
            'status' => $payload['status'],
            'delivery_observations' => $payload['delivery_observations'] ?? $porterReceivedItem->delivery_observations,
        ];

        if ($payload['status'] === 'entregado_al_destinatario') {
            $updates['delivered_at'] = now();
            $updates['delivered_by'] = $request->user()?->id;
            $updates['delivered_to_name'] = $payload['delivered_to_name'] ?: $porterReceivedItem->recipient_label;
            $updates['delivered_to_rut'] = $payload['delivered_to_rut'] ?? null;
        }

        $porterReceivedItem->update($updates);

        $this->auditService->log(
            $porterReceivedItem->fresh(),
            'cambio_estado_recepcion',
            $fromStatus,
            $payload['status'],
            $payload['delivery_observations'] ?? 'Estado actualizado en portería.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $porterReceivedItem->fresh()->load([
                'studentProfile:id,first_name,last_name,rut',
                'staff:id,full_name,rut',
                'department:id,name',
                'registeredBy:id,name',
                'deliveredBy:id,name',
            ]),
        ]);
    }

    private function validateRecipientPayload(array $payload): ?JsonResponse
    {
        return match ($payload['recipient_type']) {
            'student' => empty($payload['student_profile_id']) ? response()->json(['message' => 'Debes seleccionar una estudiante destinataria.'], 422) : null,
            'staff' => empty($payload['staff_id']) ? response()->json(['message' => 'Debes seleccionar un funcionario destinatario.'], 422) : null,
            'department' => empty($payload['department_id']) ? response()->json(['message' => 'Debes seleccionar un departamento destinatario.'], 422) : null,
            'other' => empty($payload['recipient_label']) ? response()->json(['message' => 'Debes indicar el destinatario.'], 422) : null,
            default => response()->json(['message' => 'Tipo de destinatario no válido.'], 422),
        };
    }

    private function resolveRecipientLabel(array $payload, ?StudentProfile $student): ?string
    {
        if ($payload['recipient_type'] === 'student' && $student) {
            return $student->full_name;
        }

        return $payload['recipient_label'] ?? null;
    }

    private function storeAttachment(PorterReceivedItem $item, UploadedFile $attachment): void
    {
        $path = $attachment->storePubliclyAs(
            sprintf('porter/received-items/%d', $item->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $attachment->getClientOriginalName(),
            ['disk' => 'public']
        );

        $item->update([
            'attachment_path' => $path,
            'attachment_original_name' => $attachment->getClientOriginalName(),
            'attachment_mime_type' => $attachment->getClientMimeType(),
        ]);
    }
}
