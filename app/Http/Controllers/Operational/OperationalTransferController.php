<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\SaveOperationalTransferRequest;
use App\Models\Operational\OperationalTransferDocument;
use App\Models\Operational\OperationalTransferRequest;
use App\Models\Staff;
use App\Services\Operational\OperationalTransferAccessService;
use App\Services\Operational\OperationalTransferPdfService;
use App\Services\Operational\OperationalTransferWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationalTransferController extends Controller
{
    public function __construct(
        private readonly OperationalTransferAccessService $access,
        private readonly OperationalTransferWorkflowService $workflow,
        private readonly OperationalTransferPdfService $pdf,
    ) {}

    public function catalogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OperationalTransferRequest::class);
        $user = $request->user();
        $canManage = $this->access->canManage($user);

        $staff = Staff::query()
            ->with(['cargo:id,name', 'departments:id,name'])
            ->where('active', true)
            ->when(! $canManage, fn (Builder $query) => $query->whereKey($user->staff_id))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'cargo_id'])
            ->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->full_name,
                'role' => $member->cargo?->name,
                'unit' => $member->departments->pluck('name')->implode(', '),
            ]);

        return response()->json([
            'activity_types' => OperationalTransferRequest::ACTIVITY_TYPE_OPTIONS,
            'transport_modes' => OperationalTransferRequest::TRANSPORT_MODE_OPTIONS,
            'approval_statuses' => OperationalTransferRequest::APPROVAL_STATUS_OPTIONS,
            'service_statuses' => OperationalTransferRequest::SERVICE_STATUS_OPTIONS,
            'dte_statuses' => OperationalTransferRequest::DTE_STATUS_OPTIONS,
            'payment_statuses' => OperationalTransferRequest::PAYMENT_STATUS_OPTIONS,
            'document_types' => OperationalTransferDocument::TYPE_OPTIONS,
            'staff' => $staff,
            'current_staff_id' => $user->staff_id,
            'capabilities' => [
                'create' => $user->can('create', OperationalTransferRequest::class),
                'review' => $user->hasPermission('visar_traslados_operativos') || $user->isSuperAdmin(),
                'manage' => $canManage,
                'manage_providers' => $user->hasPermission('administrar_proveedores_traslados') || $user->isSuperAdmin(),
                'export' => $user->hasPermission('exportar_traslados_operativos') || $user->isSuperAdmin(),
                'import' => $user->hasPermission('importar_traslados_operativos') || $user->isSuperAdmin(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OperationalTransferRequest::class);
        $payload = $request->validate([
            'queue' => ['nullable', Rule::in(['visible', 'mine', 'review', 'management'])],
            'search' => ['nullable', 'string', 'max:150'],
            'approval_status' => ['nullable', 'string', 'max:50'],
            'service_status' => ['nullable', 'string', 'max:50'],
            'dte_status' => ['nullable', 'string', 'max:30'],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = $this->queryForQueue($request, $payload['queue'] ?? 'visible');
        $this->applyFilters($query, $payload);

        return response()->json($query
            ->orderByDesc('transport_date')
            ->orderByDesc('id')
            ->paginate($payload['per_page'] ?? 20));
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OperationalTransferRequest::class);
        $payload = $request->validate([
            'queue' => ['nullable', Rule::in(['visible', 'mine', 'review', 'management'])],
            'search' => ['nullable', 'string', 'max:150'],
            'approval_status' => ['nullable', 'string', 'max:50'],
            'service_status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $query = $this->queryForQueue($request, $payload['queue'] ?? 'visible');
        $this->applyFilters($query, $payload);

        return response()->json(['data' => $query
            ->orderBy('transport_date')
            ->orderBy('departure_time')
            ->get()]);
    }

    public function show(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);
        $transfer = $this->workflow->load($transfer);
        if ($this->access->canManage($request->user())) {
            $transfer->makeVisible('internal_observations');
            $transfer->approvals->each->makeVisible('internal_comments');
            $transfer->operation?->makeVisible('administrative_notes');
        }

        return response()->json(['data' => $transfer]);
    }

    public function store(SaveOperationalTransferRequest $request): JsonResponse
    {
        $this->authorize('create', OperationalTransferRequest::class);
        $payload = $this->requesterPayload($request, $request->validated());
        $transfer = $this->workflow->saveDraft(new OperationalTransferRequest, $payload, $request->user(), true);

        return response()->json(['message' => 'Solicitud creada como borrador.', 'data' => $transfer], 201);
    }

    public function update(SaveOperationalTransferRequest $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);
        $payload = $this->requesterPayload($request, $request->validated());
        $transfer = $this->workflow->saveDraft($transfer, $payload, $request->user(), false);

        return response()->json(['message' => 'Solicitud actualizada.', 'data' => $transfer]);
    }

    public function submit(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('submit', $transfer);
        $payload = $request->validate(['comment' => ['nullable', 'string', 'max:3000']]);

        return response()->json([
            'message' => 'Solicitud enviada a visación.',
            'data' => $this->workflow->submit($transfer, $request->user(), $payload['comment'] ?? null),
        ]);
    }

    public function visorApprove(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('visorAct', $transfer);
        $payload = $this->decisionPayload($request, false);

        return response()->json([
            'message' => 'Solicitud visada y enviada a Administración.',
            'data' => $this->workflow->visorApprove($transfer, $request->user(), $payload['comment'] ?? null, $payload['internal_comment'] ?? null),
        ]);
    }

    public function visorObserve(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('visorAct', $transfer);
        $payload = $this->decisionPayload($request, true);

        return response()->json([
            'message' => 'Solicitud observada.',
            'data' => $this->workflow->observe($transfer, $request->user(), $payload['comment'], $payload['internal_comment'] ?? null),
        ]);
    }

    public function visorReject(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('visorAct', $transfer);
        $payload = $this->decisionPayload($request, true);

        return response()->json([
            'message' => 'Solicitud rechazada.',
            'data' => $this->workflow->reject($transfer, $request->user(), $payload['comment'], $payload['internal_comment'] ?? null),
        ]);
    }

    public function administrationApprove(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        $this->assertAdministrationStage($transfer);
        $payload = $this->decisionPayload($request, false);

        return response()->json([
            'message' => 'Solicitud aprobada por Administración.',
            'data' => $this->workflow->administrationApprove($transfer, $request->user(), $payload['comment'] ?? null, $payload['internal_comment'] ?? null),
        ]);
    }

    public function administrationObserve(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        $this->assertAdministrationStage($transfer);
        $payload = $this->decisionPayload($request, true);

        return response()->json([
            'message' => 'Solicitud observada por Administración.',
            'data' => $this->workflow->observe($transfer, $request->user(), $payload['comment'], $payload['internal_comment'] ?? null),
        ]);
    }

    public function administrationReject(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        $this->assertAdministrationStage($transfer);
        $payload = $this->decisionPayload($request, true);

        return response()->json([
            'message' => 'Solicitud rechazada por Administración.',
            'data' => $this->workflow->reject($transfer, $request->user(), $payload['comment'], $payload['internal_comment'] ?? null),
        ]);
    }

    public function updateOperation(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        $payload = $this->operationPayload($request);

        return response()->json([
            'message' => 'Gestión administrativa actualizada.',
            'data' => $this->workflow->updateOperation($transfer, $payload, $request->user()),
        ]);
    }

    public function confirm(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        $payload = $this->operationPayload($request, true);

        return response()->json([
            'message' => 'Traslado confirmado.',
            'data' => $this->workflow->confirm($transfer, $request->user(), $payload),
        ]);
    }

    public function execute(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        $payload = $request->validate(['comment' => ['nullable', 'string', 'max:3000']]);

        return response()->json([
            'message' => 'Traslado marcado como ejecutado.',
            'data' => $this->workflow->execute($transfer, $request->user(), $payload['comment'] ?? null),
        ]);
    }

    public function cancel(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('cancel', $transfer);
        $payload = $request->validate(['comment' => ['required', 'string', 'max:3000']]);

        return response()->json([
            'message' => 'Solicitud cancelada.',
            'data' => $this->workflow->cancel($transfer, $request->user(), $payload['comment']),
        ]);
    }

    public function pdf(Request $request, OperationalTransferRequest $transfer): Response
    {
        $this->authorize('view', $transfer);
        $bytes = $this->pdf->build($transfer);
        $transfer->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'pdf_exportado',
            'old_status' => $transfer->approval_status,
            'new_status' => $transfer->approval_status,
            'details' => ['generated_at' => now()->toIso8601String()],
        ]);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Solicitud_Traslado_'.$transfer->folio.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function requesterPayload(Request $request, array $payload): array
    {
        if (! $this->access->canManage($request->user())) {
            if (! $request->user()->staff_id) {
                throw ValidationException::withMessages(['requester_staff_id' => 'Tu cuenta no está vinculada a una ficha de personal.']);
            }
            $payload['requester_staff_id'] = $request->user()->staff_id;
        }

        return $payload;
    }

    private function decisionPayload(Request $request, bool $commentRequired): array
    {
        return $request->validate([
            'comment' => [$commentRequired ? 'required' : 'nullable', 'string', 'max:3000'],
            'internal_comment' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function operationPayload(Request $request, bool $confirmationRequired = false): array
    {
        return $request->validate([
            'final_cost' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'confirmation_reference' => [$confirmationRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'confirmation_notes' => ['nullable', 'string', 'max:3000'],
            'dte_status' => ['nullable', Rule::in(array_column(OperationalTransferRequest::DTE_STATUS_OPTIONS, 'value'))],
            'dte_number' => ['nullable', 'string', 'max:255'],
            'dte_received_on' => ['nullable', 'date'],
            'payment_status' => ['nullable', Rule::in(array_column(OperationalTransferRequest::PAYMENT_STATUS_OPTIONS, 'value'))],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_requested_on' => ['nullable', 'date'],
            'payment_scheduled_on' => ['nullable', 'date'],
            'paid_on' => ['nullable', 'date'],
            'administrative_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function assertAdministrationStage(OperationalTransferRequest $transfer): void
    {
        if ($transfer->approval_status !== 'pendiente_administracion') {
            throw ValidationException::withMessages(['approval_status' => 'La solicitud no está pendiente de Administración.']);
        }
    }

    private function queryForQueue(Request $request, string $queue): Builder
    {
        if ($queue === 'review') {
            abort_unless($request->user()->hasPermission('visar_traslados_operativos') || $request->user()->isSuperAdmin(), 403);
            $query = $this->access->reviewableQuery($request->user());
        } elseif ($queue === 'management') {
            abort_unless($this->access->canManage($request->user()), 403);
            $query = OperationalTransferRequest::query();
        } elseif ($queue === 'mine') {
            $query = OperationalTransferRequest::query()->where(function (Builder $inner) use ($request): void {
                $inner->where('requested_by_user_id', $request->user()->id);
                if ($request->user()->staff_id) {
                    $inner->orWhere('requester_staff_id', $request->user()->staff_id);
                }
            });
        } else {
            $query = $this->access->visibleQuery($request->user());
        }

        return $query->with([
            'requesterStaff:id,full_name,cargo_id', 'requesterStaff.cargo:id,name',
            'requestedBy:id,name,staff_id', 'visorUser:id,name,staff_id',
            'operation.provider:id,name',
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('folio', 'like', "%{$search}%")
                        ->orWhere('activity_name', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhere('requester_name_snapshot', 'like', "%{$search}%");
                });
            })
            ->when($filters['approval_status'] ?? null, fn (Builder $builder, string $value) => $builder->where('approval_status', $value))
            ->when($filters['service_status'] ?? null, fn (Builder $builder, string $value) => $builder->where('service_status', $value))
            ->when($filters['dte_status'] ?? null, fn (Builder $builder, string $value) => $builder->where('dte_status', $value))
            ->when($filters['payment_status'] ?? null, fn (Builder $builder, string $value) => $builder->where('payment_status', $value))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $value) => $builder->whereDate('transport_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $value) => $builder->whereDate('transport_date', '<=', $value));
    }
}
