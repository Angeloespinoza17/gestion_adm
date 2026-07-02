<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeDimensionRequest;
use App\Models\Pme\PmeDimension;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeDimensionController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => PmeDimension::query()->withCount(['objectives', 'actions'])->orderBy('sort_order')->get(),
        ]);
    }

    public function store(SavePmeDimensionRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canManageDimensions($request->user()), 403);

        $dimension = PmeDimension::query()->create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'active' => $request->boolean('active', true),
            'sort_order' => $request->validated('sort_order') ?? ((int) PmeDimension::query()->max('sort_order') + 1),
        ]));

        return response()->json([
            'message' => 'Dimensión PME creada correctamente.',
            'data' => $dimension,
        ], 201);
    }

    public function update(SavePmeDimensionRequest $request, PmeDimension $dimension): JsonResponse
    {
        abort_unless($this->accessService->canManageDimensions($request->user()), 403);

        $dimension->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
            'active' => $request->boolean('active', $dimension->active),
        ]));

        return response()->json([
            'message' => 'Dimensión PME actualizada correctamente.',
            'data' => $dimension->fresh(),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManageDimensions($request->user()), 403);

        $payload = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:pme_dimensiones,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($payload['items'] as $item) {
            PmeDimension::query()->whereKey($item['id'])->update([
                'sort_order' => $item['sort_order'],
                'updated_by' => $request->user()->id,
            ]);
        }

        return response()->json(['message' => 'Orden de dimensiones actualizado correctamente.']);
    }
}
