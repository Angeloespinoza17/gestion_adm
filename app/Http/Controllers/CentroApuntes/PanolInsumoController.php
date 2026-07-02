<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroApuntes\SavePanolInsumoRequest;
use App\Models\CentroApuntes\PanolInsumo;
use App\Services\CentroApuntes\PanolStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PanolInsumoController extends Controller
{
    public function __construct(
        private readonly PanolStockService $stockService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PanolInsumo::class);

        $search = trim((string) $request->query('search'));
        $items = PanolInsumo::query()
            ->with('supplier:id,name')
            ->withCount('movements')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($builder) => $builder->where('category', $request->query('category')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->boolean('critical_only'), fn ($builder) => $builder->whereColumn('current_stock', '<=', 'minimum_stock'))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function show(PanolInsumo $supply): JsonResponse
    {
        $this->authorize('view', $supply);

        return response()->json([
            'data' => $supply->load([
                'supplier:id,name',
                'movements' => fn ($query) => $query->with(['responsibleUser:id,name', 'requestedByUser:id,name', 'department:id,name'])->limit(20),
            ]),
        ]);
    }

    public function store(SavePanolInsumoRequest $request): JsonResponse
    {
        $this->authorize('create', PanolInsumo::class);

        $payload = $request->validated();
        $photo = $request->file('photo');
        unset($payload['photo']);

        $supply = PanolInsumo::query()->create(array_merge(
            $payload,
            [
                'active' => $payload['active'] ?? true,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        ));

        if ($photo instanceof UploadedFile) {
            $this->storePhoto($supply, $photo);
        }

        $this->stockService->refreshSupplyStatus($supply, $payload['status'] ?? null);
        $supply->save();

        return response()->json([
            'message' => 'Insumo registrado correctamente.',
            'data' => $supply->fresh('supplier:id,name'),
        ], 201);
    }

    public function update(SavePanolInsumoRequest $request, PanolInsumo $supply): JsonResponse
    {
        $this->authorize('update', $supply);

        $payload = $request->validated();
        $photo = $request->file('photo');
        unset($payload['photo']);

        $supply->fill(array_merge(
            $payload,
            [
                'active' => $payload['active'] ?? $supply->active,
                'updated_by' => $request->user()->id,
            ]
        ));

        if ($photo instanceof UploadedFile) {
            $this->storePhoto($supply, $photo);
        }

        $this->stockService->refreshSupplyStatus($supply, $payload['status'] ?? null);
        $supply->save();

        return response()->json([
            'message' => 'Insumo actualizado correctamente.',
            'data' => $supply->fresh('supplier:id,name'),
        ]);
    }

    public function destroy(PanolInsumo $supply): JsonResponse
    {
        $this->authorize('delete', $supply);

        if ($supply->movements()->exists() || $supply->deliveryDetails()->exists()) {
            throw ValidationException::withMessages([
                'supply' => 'No se puede eliminar un insumo con movimientos o entregas asociadas.',
            ]);
        }

        if ($supply->photo_path) {
            Storage::disk('public')->delete($supply->photo_path);
        }

        $supply->delete();

        return response()->json([
            'message' => 'Insumo eliminado correctamente.',
        ]);
    }

    private function storePhoto(PanolInsumo $supply, UploadedFile $photo): void
    {
        if ($supply->photo_path) {
            Storage::disk('public')->delete($supply->photo_path);
        }

        $path = $photo->storePubliclyAs(
            sprintf('centro-apuntes/insumos/%d', $supply->id),
            now()->format('Ymd_His') . '_' . uniqid('', true) . '.' . $photo->getClientOriginalExtension(),
            ['disk' => 'public']
        );

        $supply->photo_path = $path;
    }
}
