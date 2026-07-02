<?php

namespace App\Http\Controllers\Staff\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Permissions\SavePermissionRequestRequest;
use App\Models\Department;
use App\Models\PermissionRequest;
use App\Models\PermissionRequestDocument;
use App\Models\PermissionType;
use App\Models\Staff;
use App\Models\User;
use App\Services\Permissions\PermissionRequestAccessService;
use App\Services\Permissions\PermissionWorkflowService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PermissionRequestController extends Controller
{
    public function __construct(
        private readonly PermissionRequestAccessService $accessService,
        private readonly PermissionWorkflowService $workflowService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', PermissionRequest::class);

        $staffQuery = Staff::query()
            ->with(['cargo:id,name', 'departments:id,name,color,responsible_staff_id'])
            ->where('active', true)
            ->orderBy('full_name');

        if (
            !$user->isSuperAdmin()
            && !$user->hasPermission('revisar_permisos_rrhh')
            && !$user->hasPermission('aprobar_permisos_direccion')
            && !$user->hasPermission('revisar_permisos_equipo')
        ) {
            $staffQuery->whereKey($user->staff_id ?: 0);
        }

        return response()->json([
            'types' => PermissionType::query()->where('active', true)->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'color', 'responsible_staff_id']),
            'staff' => $staffQuery->get(['id', 'full_name', 'rut', 'cargo_id', 'institutional_email', 'contract_type', 'start_date']),
            'statuses' => PermissionRequest::STATUS_OPTIONS,
            'attendance_statuses' => PermissionRequest::ATTENDANCE_STATUS_OPTIONS,
            'payroll_statuses' => PermissionRequest::PAYROLL_STATUS_OPTIONS,
            'document_validation_statuses' => PermissionRequestDocument::VALIDATION_STATUS_OPTIONS,
            'replacement_statuses' => \App\Models\PermissionRequestReplacement::STATUS_OPTIONS,
            'steps' => PermissionRequest::STEP_OPTIONS,
            'current_user' => [
                'id' => $user->id,
                'staff_id' => $user->staff_id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'capabilities' => [
                'can_manage_types' => $user->can('manageTypes', PermissionRequest::class),
                'can_manage_watchers' => $user->can('manageWatchers', PermissionRequest::class),
                'can_review' => $user->hasPermission('revisar_permisos_equipo') || $user->hasPermission('aprobar_permisos_direccion') || $user->hasPermission('revisar_permisos_rrhh') || $user->isSuperAdmin(),
                'can_validate_documents' => $user->can('validateDocuments', PermissionRequest::class),
                'can_manage_replacements' => $user->can('manageReplacements', PermissionRequest::class),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PermissionRequest::class);

        $query = $this->baseQueryForRequest($request);

        return response()->json(
            $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate((int) $request->query('per_page', 15))
        );
    }

    public function show(PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('view', $permissionRequest);

        return response()->json([
            'data' => $permissionRequest->load([
                'staff:id,full_name,rut,cargo_id,institutional_email,personal_email,start_date,workday,contract_hours',
                'staff.cargo:id,name',
                'staff.departments:id,name,color,responsible_staff_id',
                'permissionType',
                'departments:id,name,color,responsible_staff_id',
                'requestedBy:id,name,email,staff_id',
                'createdBy:id,name',
                'updatedBy:id,name',
                'directManagerUser:id,name,email,staff_id',
                'approvals.approverUser:id,name,email',
                'documents.uploadedByUser:id,name,email',
                'documents.validatedByUser:id,name,email',
                'replacements.replacedStaff:id,full_name',
                'replacements.replacementStaff:id,full_name',
                'watchers.user:id,name,email',
                'logs.user:id,name,email',
            ]),
        ]);
    }

    public function store(SavePermissionRequestRequest $request): JsonResponse
    {
        $this->authorize('create', PermissionRequest::class);

        $payload = $request->validated();
        $user = $request->user();

        if (
            !$user->isSuperAdmin()
            && !$user->hasPermission('revisar_permisos_rrhh')
            && !$user->hasPermission('aprobar_permisos_direccion')
            && (int) ($payload['staff_id'] ?? 0) !== (int) $user->staff_id
        ) {
            return response()->json(['message' => 'Solo puedes crear solicitudes para tu ficha de funcionario.'], 403);
        }

        $permissionRequest = $this->workflowService->saveDraft(new PermissionRequest(), $payload, $user, true);

        if (!empty($payload['submit'])) {
            $permissionRequest = $this->workflowService->submit($permissionRequest, $user);
        }

        return response()->json([
            'message' => !empty($payload['submit'])
                ? 'Solicitud creada y enviada a revisión.'
                : 'Solicitud de permiso creada correctamente.',
            'data' => $permissionRequest,
        ], 201);
    }

    public function update(SavePermissionRequestRequest $request, PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('update', $permissionRequest);

        $payload = $request->validated();
        $permissionRequest = $this->workflowService->saveDraft($permissionRequest, $payload, $request->user(), false);

        if (!empty($payload['submit'])) {
            $this->authorize('submit', $permissionRequest);
            $permissionRequest = $this->workflowService->submit($permissionRequest, $request->user());
        }

        return response()->json([
            'message' => !empty($payload['submit'])
                ? 'Solicitud actualizada y enviada a revisión.'
                : 'Solicitud actualizada correctamente.',
            'data' => $permissionRequest,
        ]);
    }

    public function submit(Request $request, PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('submit', $permissionRequest);

        $payload = $request->validate([
            'comments' => ['nullable', 'string'],
        ]);

        $permissionRequest = $this->workflowService->submit($permissionRequest, $request->user(), $payload['comments'] ?? null);

        return response()->json([
            'message' => 'Solicitud enviada a revisión.',
            'data' => $permissionRequest,
        ]);
    }

    public function approve(Request $request, PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('approve', $permissionRequest);

        $payload = $request->validate([
            'comments' => ['nullable', 'string'],
            'internal_comments' => ['nullable', 'string'],
            'visible_observations' => ['nullable', 'string'],
            'internal_observations' => ['nullable', 'string'],
            'with_pay' => ['nullable', 'boolean'],
            'affects_salary' => ['nullable', 'boolean'],
            'affects_attendance' => ['nullable', 'boolean'],
            'salary_discount_hours' => ['nullable', 'numeric', 'min:0'],
            'salary_discount_days' => ['nullable', 'numeric', 'min:0'],
            'payroll_status' => ['nullable', Rule::in(array_column(PermissionRequest::PAYROLL_STATUS_OPTIONS, 'value'))],
        ]);

        $permissionRequest = $this->workflowService->approve($permissionRequest, $request->user(), $payload);

        return response()->json([
            'message' => 'La acción de aprobación fue registrada correctamente.',
            'data' => $permissionRequest,
        ]);
    }

    public function reject(Request $request, PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('approve', $permissionRequest);

        $payload = $request->validate([
            'comments' => ['required', 'string'],
            'internal_comments' => ['nullable', 'string'],
        ]);

        $permissionRequest = $this->workflowService->reject(
            $permissionRequest,
            $request->user(),
            $payload['comments'],
            $payload['internal_comments'] ?? null,
        );

        return response()->json([
            'message' => 'Solicitud rechazada correctamente.',
            'data' => $permissionRequest,
        ]);
    }

    public function observe(Request $request, PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('approve', $permissionRequest);

        $payload = $request->validate([
            'comments' => ['required', 'string'],
            'internal_comments' => ['nullable', 'string'],
        ]);

        $permissionRequest = $this->workflowService->observe(
            $permissionRequest,
            $request->user(),
            $payload['comments'],
            $payload['internal_comments'] ?? null,
        );

        return response()->json([
            'message' => 'Solicitud observada correctamente.',
            'data' => $permissionRequest,
        ]);
    }

    public function returnToEmployee(Request $request, PermissionRequest $permissionRequest): JsonResponse
    {
        return $this->observe($request, $permissionRequest);
    }

    public function cancel(Request $request, PermissionRequest $permissionRequest): JsonResponse
    {
        $this->authorize('cancel', $permissionRequest);

        $payload = $request->validate([
            'comments' => ['nullable', 'string'],
        ]);

        $permissionRequest = $this->workflowService->cancel($permissionRequest, $request->user(), $payload['comments'] ?? null);

        return response()->json([
            'message' => 'Solicitud cancelada correctamente.',
            'data' => $permissionRequest,
        ]);
    }

    public function execute(Request $request, PermissionRequest $permissionRequest): JsonResponse
    {
        if (
            !$request->user()->isSuperAdmin()
            && !$request->user()->hasPermission('revisar_permisos_rrhh')
            && !$request->user()->hasPermission('aprobar_permisos_direccion')
        ) {
            return response()->json(['message' => 'No autorizado para ejecutar solicitudes.'], 403);
        }

        $payload = $request->validate([
            'comments' => ['nullable', 'string'],
        ]);

        $permissionRequest = $this->workflowService->execute($permissionRequest, $request->user(), $payload['comments'] ?? null);

        return response()->json([
            'message' => 'Solicitud marcada como ejecutada.',
            'data' => $permissionRequest,
        ]);
    }

    public function staffSummary(Request $request, Staff $staff): JsonResponse
    {
        $user = $request->user();
        $query = $this->accessService->visibleQuery($user)->where('staff_id', $staff->id);

        if (!$query->exists() && (int) $user->staff_id !== (int) $staff->id && !$user->hasPermission('ver_funcionarios') && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $year = (int) $request->query('year', now()->year);

        $baseQuery = PermissionRequest::query()->where('staff_id', $staff->id);

        return response()->json([
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'aprobados' => (clone $baseQuery)->where('status', 'aprobado')->count(),
                'rechazados' => (clone $baseQuery)->where('status', 'rechazado')->count(),
                'pendientes' => (clone $baseQuery)->whereIn('status', ['ingresado', 'pendiente_jefatura', 'pendiente_direccion', 'pendiente_rrhh', 'observado'])->count(),
                'con_goce' => (clone $baseQuery)->where('with_pay', true)->count(),
                'sin_goce' => (clone $baseQuery)->where('with_pay', false)->count(),
            ],
            'annual_by_type' => PermissionRequest::query()
                ->select('permission_type_id', DB::raw('SUM(COALESCE(duration_days, 0)) as used_days'), DB::raw('SUM(COALESCE(duration_hours, 0)) as used_hours'))
                ->where('staff_id', $staff->id)
                ->whereYear('start_date', $year)
                ->groupBy('permission_type_id')
                ->with('permissionType:id,name')
                ->get(),
            'recent' => PermissionRequest::query()
                ->with(['permissionType:id,name'])
                ->where('staff_id', $staff->id)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
        ]);
    }

    private function baseQueryForRequest(Request $request): Builder
    {
        $queueOnly = filter_var($request->query('review_queue'), FILTER_VALIDATE_BOOLEAN);
        $query = $queueOnly
            ? $this->accessService->reviewableQuery($request->user())
            : $this->accessService->visibleQuery($request->user());

        $search = trim((string) $request->query('search'));
        $staffId = $request->query('staff_id');
        $departmentId = $request->query('department_id');
        $permissionTypeId = $request->query('permission_type_id');
        $status = trim((string) $request->query('status'));
        $withPay = $request->query('with_pay');
        $requiresReplacement = $request->query('requires_replacement');
        $affectsSalary = $request->query('affects_salary');
        $month = $request->query('month');
        $year = $request->query('year');
        $urgency = $request->query('urgency');
        $mineOnly = filter_var($request->query('mine_only'), FILTER_VALIDATE_BOOLEAN);
        $upcoming = filter_var($request->query('upcoming'), FILTER_VALIDATE_BOOLEAN);

        $query->with([
            'staff:id,full_name,rut,cargo_id',
            'staff.cargo:id,name',
            'permissionType:id,name,requires_replacement,affects_salary,affects_attendance',
            'departments:id,name,color,responsible_staff_id',
            'requestedBy:id,name,email,staff_id',
            'directManagerUser:id,name,email,staff_id',
            'documents:id,permission_request_id,validation_status',
            'replacements:id,permission_request_id,status,replacement_staff_id,replaced_staff_id',
        ]);

        $query
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->where('reason', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('staff', fn (Builder $staffQuery) => $staffQuery->where('full_name', 'like', "%{$search}%")->orWhere('rut', 'like', "%{$search}%"))
                        ->orWhereHas('permissionType', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($staffId, fn (Builder $query) => $query->where('staff_id', $staffId))
            ->when($departmentId, fn (Builder $query) => $query->whereHas('departments', fn (Builder $departmentQuery) => $departmentQuery->where('departments.id', $departmentId)))
            ->when($permissionTypeId, fn (Builder $query) => $query->where('permission_type_id', $permissionTypeId))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($withPay !== null && $withPay !== '', fn (Builder $query) => $query->where('with_pay', filter_var($withPay, FILTER_VALIDATE_BOOLEAN)))
            ->when($requiresReplacement !== null && $requiresReplacement !== '', fn (Builder $query) => $query->where('requires_replacement', filter_var($requiresReplacement, FILTER_VALIDATE_BOOLEAN)))
            ->when($affectsSalary !== null && $affectsSalary !== '', fn (Builder $query) => $query->where('affects_salary', filter_var($affectsSalary, FILTER_VALIDATE_BOOLEAN)))
            ->when($urgency !== null && $urgency !== '', fn (Builder $query) => $query->where('urgency', filter_var($urgency, FILTER_VALIDATE_BOOLEAN)))
            ->when($month && $year, fn (Builder $query) => $query->forMonth((int) $year, (int) $month))
            ->when($mineOnly && $request->user()->staff_id, fn (Builder $query) => $query->where('staff_id', $request->user()->staff_id))
            ->when($upcoming, fn (Builder $query) => $query->whereIn('status', ['aprobado', 'ejecutado'])->whereBetween('start_date', [Carbon::today(), Carbon::today()->copy()->addDays(15)]));

        return $query;
    }
}
