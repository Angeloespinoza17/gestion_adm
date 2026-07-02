<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaPlanRequest;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Services\Convivencia\ConvivenciaPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaPlanController extends Controller
{
    public function __construct(
        private readonly ConvivenciaPlanService $planService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaPlan::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applyPlanVisibility(
                ConvivenciaPlan::query()->with([
                    'academicYear:id,name,year',
                    'responsibleUser:id,name',
                    'responsibleStaff:id,full_name',
                ])->withCount('actions'),
                $request->user(),
            );

        $search = trim((string) $request->query('search'));
        $query
            ->when($search !== '', fn ($builder) => $builder->where('name', 'like', "%{$search}%"))
            ->when($request->query('academic_year_id'), fn ($builder, $value) => $builder->where('academic_year_id', $value))
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('responsible_user_id'), fn ($builder, $value) => $builder->where('responsible_user_id', $value));

        return response()->json($query->latest('starts_on')->paginate((int) $request->query('per_page', 12)));
    }

    public function store(SaveConvivenciaPlanRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaPlan::class);

        $plan = $this->planService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Plan registrado correctamente.',
            'data' => $plan,
        ], 201);
    }

    public function show(ConvivenciaPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return response()->json([
            'data' => $plan->load([
                'academicYear:id,name,year',
                'responsibleUser:id,name,email',
                'responsibleStaff:id,full_name',
                'actions.dimension:id,name',
                'actions.responsibleUser:id,name',
                'actions.responsibleStaff:id,full_name',
                'actions.responsibleDepartment:id,name',
                'attachments.uploadedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveConvivenciaPlanRequest $request, ConvivenciaPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $updated = $this->planService->update($plan, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Plan actualizado correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(ConvivenciaPlan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return response()->json([
            'message' => 'Plan archivado correctamente.',
        ]);
    }
}
