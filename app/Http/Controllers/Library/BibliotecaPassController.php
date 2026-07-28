<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaPaseRequest;
use App\Models\Library\BibliotecaPase;
use App\Services\Library\BibliotecaAccessService;
use App\Services\Library\BibliotecaPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaPassController extends Controller
{
    public function __construct(
        private readonly BibliotecaPassService $service,
        private readonly BibliotecaAccessService $accessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);
        $this->service->refreshExpired();
        $search = trim((string) $request->query('search'));

        $query = BibliotecaPase::query()
            ->with([
                'student:id,first_name,last_name,registered_name,rut',
                'courseSection:id,display_name',
                'professor:id,full_name,rut',
                'issuedBy:id,name',
                'usedBy:id,name',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('pass_code', 'like', "%{$search}%")
                        ->orWhere('student_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('professor_name_snapshot', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('date'), fn ($builder) => $builder->whereDate('valid_from', $request->query('date')));

        return response()->json($query->latest('issued_at')->paginate((int) $request->query('per_page', 15)));
    }

    public function store(SaveBibliotecaPaseRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canManagePasses($request->user()), 403);

        return response()->json([
            'message' => 'Pase de biblioteca emitido correctamente.',
            'data' => $this->service->create($request->validated(), $request->user()),
        ], 201);
    }

    public function update(
        SaveBibliotecaPaseRequest $request,
        BibliotecaPase $pase,
    ): JsonResponse {
        abort_unless($this->accessService->canManagePasses($request->user()), 403);

        return response()->json([
            'message' => 'Pase actualizado correctamente.',
            'data' => $this->service->update($pase, $request->validated(), $request->user()),
        ]);
    }

    public function transition(Request $request, BibliotecaPase $pase, string $status): JsonResponse
    {
        abort_unless($this->accessService->canManagePasses($request->user()), 403);

        return response()->json([
            'message' => $status === 'utilizado' ? 'Pase marcado como utilizado.' : 'Pase anulado.',
            'data' => $this->service->transition($pase, $status, $request->user()),
        ]);
    }
}
