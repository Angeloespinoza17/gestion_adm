<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\RenewBibliotecaPrestamoRequest;
use App\Http\Requests\Library\ReturnBibliotecaPrestamoRequest;
use App\Http\Requests\Library\SaveBibliotecaPrestamoRequest;
use App\Models\Library\BibliotecaPrestamo;
use App\Services\Library\BibliotecaLoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaLoanController extends Controller
{
    public function __construct(
        private readonly BibliotecaLoanService $loanService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaPrestamo::class);
        $this->loanService->refreshStatuses();

        $search = trim((string) $request->query('search'));

        $query = BibliotecaPrestamo::query()
            ->with([
                'obra:id,title,category',
                'ejemplar:id,code',
                'student:id,first_name,last_name',
                'staff:id,full_name',
                'courseSection:id,display_name',
                'deliveredBy:id,name',
                'receivedBy:id,name',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('loan_code', 'like', "%{$search}%")
                        ->orWhere('borrower_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('course_name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('obra', fn ($obraQuery) => $obraQuery->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('student_profile_id'), fn ($builder) => $builder->where('student_profile_id', $request->query('student_profile_id')))
            ->when($request->filled('staff_id'), fn ($builder) => $builder->where('staff_id', $request->query('staff_id')))
            ->when($request->filled('course_section_id'), fn ($builder) => $builder->where('course_section_id', $request->query('course_section_id')))
            ->when($request->filled('biblioteca_ejemplar_id'), fn ($builder) => $builder->where('biblioteca_ejemplar_id', $request->query('biblioteca_ejemplar_id')))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('borrowed_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('borrowed_at', '<=', $request->query('date_to')))
            ->when($request->boolean('overdue_only'), fn ($builder) => $builder->where('status', 'vencido'));

        return response()->json($query->latest('borrowed_at')->paginate((int) $request->query('per_page', 15)));
    }

    public function show(BibliotecaPrestamo $prestamo): JsonResponse
    {
        $this->authorize('view', $prestamo);

        return response()->json([
            'data' => $prestamo->load([
                'obra',
                'ejemplar.movimientos',
                'student',
                'staff',
                'courseSection',
                'academicYear',
                'deliveredBy:id,name',
                'receivedBy:id,name',
                'reservas',
            ]),
        ]);
    }

    public function store(SaveBibliotecaPrestamoRequest $request): JsonResponse
    {
        $this->authorize('create', BibliotecaPrestamo::class);

        $loan = $this->loanService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Préstamo registrado correctamente.',
            'data' => $loan,
        ], 201);
    }

    public function renew(RenewBibliotecaPrestamoRequest $request, BibliotecaPrestamo $prestamo): JsonResponse
    {
        $this->authorize('update', $prestamo);

        return response()->json([
            'message' => 'Préstamo renovado correctamente.',
            'data' => $this->loanService->renew($prestamo, $request->validated(), $request->user()),
        ]);
    }

    public function return(ReturnBibliotecaPrestamoRequest $request, BibliotecaPrestamo $prestamo): JsonResponse
    {
        $this->authorize('update', $prestamo);

        return response()->json([
            'message' => 'Devolución registrada correctamente.',
            'data' => $this->loanService->registerReturn($prestamo, $request->validated(), $request->user()),
        ]);
    }

    public function cancel(Request $request, BibliotecaPrestamo $prestamo): JsonResponse
    {
        $this->authorize('update', $prestamo);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        return response()->json([
            'message' => 'Préstamo cancelado correctamente.',
            'data' => $this->loanService->cancel($prestamo, $request->user(), $payload['notes'] ?? null),
        ]);
    }
}
