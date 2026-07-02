<?php

namespace App\Http\Controllers\Informatica;

use App\Http\Controllers\Controller;
use App\Http\Requests\Informatica\ReturnItEquipmentLoanRequest;
use App\Http\Requests\Informatica\SaveItEquipmentLoanRequest;
use App\Models\It\ItEquipmentLoan;
use App\Services\Informatica\ItEquipmentLoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItEquipmentLoanController extends Controller
{
    public function __construct(
        private readonly ItEquipmentLoanService $loanService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ItEquipmentLoan::class);
        $this->loanService->refreshOverdueStatuses();

        $search = trim((string) $request->query('search'));

        $query = ItEquipmentLoan::query()
            ->with([
                'equipment:id,internal_code,equipment_type,brand,model,status',
                'deliveredBy:id,name',
                'receivedBy:id,name',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('loan_code', 'like', "%{$search}%")
                        ->orWhere('requester_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('requester_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('requester_contact_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('equipment', function ($equipmentQuery) use ($search) {
                            $equipmentQuery
                                ->where('internal_code', 'like', "%{$search}%")
                                ->orWhere('brand', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('it_equipment_id'), fn ($builder) => $builder->where('it_equipment_id', $request->query('it_equipment_id')))
            ->when($request->filled('requester_type'), fn ($builder) => $builder->where('requester_type', $request->query('requester_type')))
            ->when($request->filled('delivered_by_user_id'), fn ($builder) => $builder->where('delivered_by_user_id', $request->query('delivered_by_user_id')))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('borrowed_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('borrowed_at', '<=', $request->query('date_to')))
            ->when($request->filled('due_date_from'), fn ($builder) => $builder->whereDate('due_at', '>=', $request->query('due_date_from')))
            ->when($request->filled('due_date_to'), fn ($builder) => $builder->whereDate('due_at', '<=', $request->query('due_date_to')))
            ->when($request->boolean('only_overdue'), fn ($builder) => $builder->where('status', 'atrasado'))
            ->when($request->boolean('only_active'), fn ($builder) => $builder->whereIn('status', ['activo', 'atrasado']));

        return response()->json([
            'items' => $query->latest('borrowed_at')->paginate((int) $request->query('per_page', 15)),
            'summary' => [
                'active' => ItEquipmentLoan::query()->active()->count(),
                'overdue' => ItEquipmentLoan::query()->overdue()->count(),
                'returned_month' => ItEquipmentLoan::query()->where('status', 'devuelto')->whereDate('returned_at', '>=', now()->startOfMonth())->count(),
                'cancelled_month' => ItEquipmentLoan::query()->where('status', 'cancelado')->whereDate('updated_at', '>=', now()->startOfMonth())->count(),
            ],
        ]);
    }

    public function show(ItEquipmentLoan $loan): JsonResponse
    {
        $this->authorize('view', $loan);

        return response()->json([
            'data' => $loan->load([
                'equipment:id,internal_code,equipment_type,brand,model,status,location_name',
                'requesterUser:id,name,email',
                'requesterStaff:id,full_name,rut,institutional_email,phone',
                'requesterStudent:id,first_name,last_name,registered_name,rut,guardian_phone,phone',
                'deliveredBy:id,name',
                'receivedBy:id,name',
                'attachments.uploadedBy:id,name',
            ]),
        ]);
    }

    public function store(SaveItEquipmentLoanRequest $request): JsonResponse
    {
        $this->authorize('create', ItEquipmentLoan::class);

        $loan = $this->loanService->create(
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'message' => 'Préstamo registrado correctamente.',
            'data' => $loan,
        ], 201);
    }

    public function registerReturn(ReturnItEquipmentLoanRequest $request, ItEquipmentLoan $loan): JsonResponse
    {
        $this->authorize('update', $loan);

        $loan = $this->loanService->registerReturn(
            $loan,
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'message' => 'Devolución registrada correctamente.',
            'data' => $loan,
        ]);
    }

    public function cancel(Request $request, ItEquipmentLoan $loan): JsonResponse
    {
        $this->authorize('update', $loan);

        $payload = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $loan = $this->loanService->cancel($loan, $request->user(), $payload['notes'] ?? null);

        return response()->json([
            'message' => 'Préstamo cancelado correctamente.',
            'data' => $loan,
        ]);
    }
}
