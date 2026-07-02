<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaPlanLectorRequest;
use App\Models\Library\BibliotecaPlanLector;
use App\Services\Library\BibliotecaPlanLectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaPlanLectorController extends Controller
{
    public function __construct(
        private readonly BibliotecaPlanLectorService $planService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaPlanLector::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaPlanLector::query()
            ->with([
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'responsibleStaff:id,full_name',
                'obra:id,title,available_copies,category',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('subject', 'like', "%{$search}%")
                        ->orWhere('period', 'like', "%{$search}%")
                        ->orWhereHas('obra', fn ($obraQuery) => $obraQuery->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('academic_year_id'), fn ($builder) => $builder->where('academic_year_id', $request->query('academic_year_id')))
            ->when($request->filled('course_section_id'), fn ($builder) => $builder->where('course_section_id', $request->query('course_section_id')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')));

        return response()->json($query->orderByDesc('start_date')->paginate((int) $request->query('per_page', 15)));
    }

    public function show(BibliotecaPlanLector $planLector): JsonResponse
    {
        $this->authorize('view', $planLector);

        return response()->json([
            'data' => $planLector->load(['academicYear', 'courseSection', 'responsibleStaff', 'obra']),
        ]);
    }

    public function store(SaveBibliotecaPlanLectorRequest $request): JsonResponse
    {
        $this->authorize('create', BibliotecaPlanLector::class);

        return response()->json([
            'message' => 'Plan lector registrado correctamente.',
            'data' => $this->planService->store($request->validated(), $request->user()),
        ], 201);
    }

    public function update(SaveBibliotecaPlanLectorRequest $request, BibliotecaPlanLector $planLector): JsonResponse
    {
        $this->authorize('update', $planLector);

        return response()->json([
            'message' => 'Plan lector actualizado correctamente.',
            'data' => $this->planService->update($planLector, $request->validated(), $request->user()),
        ]);
    }

    public function massLoan(Request $request, BibliotecaPlanLector $planLector): JsonResponse
    {
        $this->authorize('update', $planLector);

        $payload = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
            'borrowed_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'delivered_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return response()->json([
            'message' => 'Préstamo masivo de plan lector ejecutado correctamente.',
            'data' => $this->planService->registerMassLoan($planLector, $payload, $request->user()),
        ]);
    }
}
