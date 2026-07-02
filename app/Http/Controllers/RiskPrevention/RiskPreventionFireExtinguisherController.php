<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionFireExtinguisherRequest;
use App\Models\RiskPrevention\RiskPreventionFireExtinguisher;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskPreventionFireExtinguisherController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionFireExtinguisher::class);
        $this->accessService->refreshDynamicStatuses();

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $query = RiskPreventionFireExtinguisher::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('extinguisher_type', 'like', "%{$search}%")
                        ->orWhere('building', 'like', "%{$search}%")
                        ->orWhere('floor', 'like', "%{$search}%")
                        ->orWhere('dependency_name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('expires_at')
            ->paginate((int) $request->query('per_page', 12));

        return response()->json($query);
    }

    public function store(SaveRiskPreventionFireExtinguisherRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionFireExtinguisher::class);

        $fireExtinguisher = RiskPreventionFireExtinguisher::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        ));

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Extintor registrado correctamente.',
            'data' => $fireExtinguisher->fresh(),
        ], 201);
    }

    public function show(RiskPreventionFireExtinguisher $fireExtinguisher): JsonResponse
    {
        $this->authorize('view', $fireExtinguisher);

        return response()->json([
            'data' => $fireExtinguisher,
        ]);
    }

    public function update(SaveRiskPreventionFireExtinguisherRequest $request, RiskPreventionFireExtinguisher $fireExtinguisher): JsonResponse
    {
        $this->authorize('update', $fireExtinguisher);

        $fireExtinguisher->update(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id],
        ));

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Extintor actualizado correctamente.',
            'data' => $fireExtinguisher->fresh(),
        ]);
    }

    public function destroy(RiskPreventionFireExtinguisher $fireExtinguisher): JsonResponse
    {
        $this->authorize('delete', $fireExtinguisher);
        $fireExtinguisher->delete();

        return response()->json([
            'message' => 'Extintor eliminado correctamente.',
        ]);
    }
}
