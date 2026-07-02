<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaReservaRequest;
use App\Models\Library\BibliotecaReserva;
use App\Services\Library\BibliotecaReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaReservationController extends Controller
{
    public function __construct(
        private readonly BibliotecaReservationService $reservationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaReserva::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaReserva::query()
            ->with([
                'obra:id,title,category,material_type',
                'ejemplar:id,code',
                'prestamo:id,loan_code,status,due_at',
                'student:id,first_name,last_name',
                'staff:id,full_name',
                'courseSection:id,display_name',
                'requestedBy:id,name',
                'responsible:id,name',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('reservation_code', 'like', "%{$search}%")
                        ->orWhere('resource_type', 'like', "%{$search}%")
                        ->orWhereHas('obra', fn ($obraQuery) => $obraQuery->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('resource_type'), fn ($builder) => $builder->where('resource_type', $request->query('resource_type')))
            ->when($request->filled('student_profile_id'), fn ($builder) => $builder->where('student_profile_id', $request->query('student_profile_id')))
            ->when($request->filled('staff_id'), fn ($builder) => $builder->where('staff_id', $request->query('staff_id')))
            ->when($request->filled('course_section_id'), fn ($builder) => $builder->where('course_section_id', $request->query('course_section_id')))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('requested_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('requested_at', '<=', $request->query('date_to')));

        return response()->json($query->latest('requested_at')->paginate((int) $request->query('per_page', 15)));
    }

    public function show(BibliotecaReserva $reserva): JsonResponse
    {
        $this->authorize('view', $reserva);

        return response()->json([
            'data' => $reserva->load([
                'obra',
                'ejemplar.movimientos',
                'prestamo',
                'student',
                'staff',
                'courseSection',
                'requestedBy:id,name',
                'responsible:id,name',
                'deliveredBy:id,name',
                'receivedBy:id,name',
            ]),
        ]);
    }

    public function store(SaveBibliotecaReservaRequest $request): JsonResponse
    {
        $this->authorize('create', BibliotecaReserva::class);

        return response()->json([
            'message' => 'Reserva registrada correctamente.',
            'data' => $this->reservationService->create($request->validated(), $request->user()),
        ], 201);
    }

    public function approve(Request $request, BibliotecaReserva $reserva): JsonResponse
    {
        $this->authorize('update', $reserva);

        $payload = $request->validate([
            'approval_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Reserva aprobada correctamente.',
            'data' => $this->reservationService->transition($reserva, 'aprobada', $request->user(), $payload),
        ]);
    }

    public function reject(Request $request, BibliotecaReserva $reserva): JsonResponse
    {
        $this->authorize('update', $reserva);

        $payload = $request->validate([
            'approval_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Reserva rechazada correctamente.',
            'data' => $this->reservationService->transition($reserva, 'rechazada', $request->user(), $payload),
        ]);
    }

    public function checkout(Request $request, BibliotecaReserva $reserva): JsonResponse
    {
        $this->authorize('update', $reserva);

        $payload = $request->validate([
            'borrowed_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'delivered_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Reserva entregada correctamente.',
            'data' => $this->reservationService->transition($reserva, 'retirada', $request->user(), $payload),
        ]);
    }

    public function registerReturn(Request $request, BibliotecaReserva $reserva): JsonResponse
    {
        $this->authorize('update', $reserva);

        $payload = $request->validate([
            'returned_at' => ['nullable', 'date'],
            'received_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'returned_condition' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Reserva cerrada correctamente.',
            'data' => $this->reservationService->transition($reserva, 'devuelta', $request->user(), $payload),
        ]);
    }

    public function cancel(Request $request, BibliotecaReserva $reserva): JsonResponse
    {
        $this->authorize('update', $reserva);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        return response()->json([
            'message' => 'Reserva cancelada correctamente.',
            'data' => $this->reservationService->transition($reserva, 'cancelada', $request->user(), $payload),
        ]);
    }
}
