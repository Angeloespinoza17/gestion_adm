<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaMaterialRequest;
use App\Http\Requests\Library\SaveBibliotecaObraRequest;
use App\Models\Library\BibliotecaCategoria;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaSubcategoria;
use App\Models\Library\BibliotecaUbicacion;
use App\Services\Library\BibliotecaAccessService;
use App\Services\Library\BibliotecaCodeService;
use App\Services\Library\BibliotecaInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BibliotecaCatalogController extends Controller
{
    public function __construct(
        private readonly BibliotecaCodeService $codeService,
        private readonly BibliotecaInventoryService $inventoryService,
        private readonly BibliotecaAccessService $accessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaObra::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaObra::query()
            ->with([
                'recommendedCourse:id,display_name',
                'categoria:id,name,code,color',
                'subcategoria:id,biblioteca_categoria_id,name',
                'ubicacion:id,name,code',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('main_author', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('subcategory', 'like', "%{$search}%")
                        ->orWhere('internal_code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('material_type'), fn ($builder) => $builder->where('material_type', $request->query('material_type')))
            ->when($request->filled('category'), fn ($builder) => $builder->where('category', $request->query('category')))
            ->when($request->filled('biblioteca_categoria_id'), fn ($builder) => $builder->where('biblioteca_categoria_id', $request->query('biblioteca_categoria_id')))
            ->when($request->filled('recommended_course_section_id'), fn ($builder) => $builder->where('recommended_course_section_id', $request->query('recommended_course_section_id')))
            ->when($request->filled('general_status'), fn ($builder) => $builder->where('general_status', $request->query('general_status')))
            ->when($request->filled('physical_location'), fn ($builder) => $builder->where('physical_location', 'like', '%'.$request->query('physical_location').'%'))
            ->when($request->boolean('available_only'), fn ($builder) => $builder->where('available_copies', '>', 0));

        return response()->json($query->orderBy('title')->paginate((int) $request->query('per_page', 12)));
    }

    public function show(BibliotecaObra $obra): JsonResponse
    {
        $this->authorize('view', $obra);

        return response()->json([
            'data' => $obra->load([
                'recommendedCourse:id,display_name',
                'categoria',
                'subcategoria',
                'ubicacion',
                'ejemplares.movimientos',
                'prestamos.obra:id,title',
                'prestamos.ejemplar:id,code',
                'reservas.obra:id,title',
                'planesLectores.courseSection:id,display_name',
            ]),
        ]);
    }

    public function store(SaveBibliotecaObraRequest $request): JsonResponse
    {
        $this->authorize('create', BibliotecaObra::class);

        $validated = $request->validated();
        $validated = $this->normalizeRelations($validated);
        $quantity = (int) ($validated['quantity'] ?? 1);
        unset($validated['quantity'], $validated['additional_quantity']);

        $obra = DB::transaction(function () use ($request, $validated, $quantity) {
            $validated['internal_code'] = ($validated['internal_code'] ?? null) ?: $this->codeService->next('OBR');

            $obra = BibliotecaObra::query()->create(array_merge($validated, [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]));

            $this->inventoryService->addCopies($obra, $quantity, $request->user());

            return $obra;
        });

        return response()->json([
            'message' => 'Obra bibliográfica registrada correctamente.',
            'data' => $obra->fresh(['recommendedCourse:id,display_name', 'categoria', 'subcategoria', 'ubicacion', 'ejemplares']),
        ], 201);
    }

    public function storeMaterial(SaveBibliotecaMaterialRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canManageMaterials($request->user()), 403);

        $validated = $this->normalizeRelations($request->validated());
        $quantity = (int) $validated['quantity'];
        $physicalState = $validated['physical_state'];
        $copyDefaults = [
            'ingress_date' => $validated['ingress_date'] ?? now()->toDateString(),
            'origin' => $validated['origin'],
            'biblioteca_ubicacion_id' => $validated['biblioteca_ubicacion_id'] ?? null,
            'physical_location' => $validated['physical_location'] ?? null,
            'physical_state' => $physicalState,
            'availability_status' => in_array($physicalState, ['danado', 'en_reparacion', 'perdido', 'dado_de_baja'], true)
                ? $physicalState
                : 'disponible',
            'observations' => $validated['observations'] ?? null,
        ];

        unset(
            $validated['quantity'],
            $validated['ingress_date'],
            $validated['origin'],
            $validated['physical_state'],
        );

        $obra = DB::transaction(function () use ($request, $validated, $quantity, $copyDefaults) {
            $validated['internal_code'] = ($validated['internal_code'] ?? null) ?: $this->codeService->next('MAT');
            $validated['main_author'] = ($validated['main_author'] ?? null) ?: 'Sin fabricante informado';

            $obra = BibliotecaObra::query()->create(array_merge($validated, [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]));

            $this->inventoryService->addCopies($obra, $quantity, $request->user(), $copyDefaults);

            return $obra;
        });

        return response()->json([
            'message' => 'Material y unidades registrados correctamente.',
            'data' => $obra->fresh(['categoria', 'subcategoria', 'ubicacion', 'ejemplares']),
        ], 201);
    }

    public function update(SaveBibliotecaObraRequest $request, BibliotecaObra $obra): JsonResponse
    {
        $this->authorize('update', $obra);

        $validated = $request->validated();
        $validated = $this->normalizeRelations($validated);
        $additionalQuantity = (int) ($validated['additional_quantity'] ?? 0);
        unset($validated['quantity'], $validated['additional_quantity']);

        DB::transaction(function () use ($obra, $validated, $additionalQuantity, $request) {
            $validated['internal_code'] = ($validated['internal_code'] ?? null) ?: $obra->internal_code;
            $obra->fill(array_merge($validated, ['updated_by' => $request->user()->id]))->save();
            $this->inventoryService->addCopies($obra, $additionalQuantity, $request->user());
        });

        return response()->json([
            'message' => 'Obra bibliográfica actualizada correctamente.',
            'data' => $obra->fresh(['recommendedCourse:id,display_name', 'categoria', 'subcategoria', 'ubicacion', 'ejemplares']),
        ]);
    }

    public function destroy(BibliotecaObra $obra): JsonResponse
    {
        $this->authorize('delete', $obra);

        if ($obra->ejemplares()->exists() || $obra->prestamos()->exists() || $obra->reservas()->exists()) {
            throw ValidationException::withMessages([
                'obra' => 'No se puede eliminar una obra con ejemplares, préstamos o reservas asociadas.',
            ]);
        }

        $obra->delete();

        return response()->json([
            'message' => 'Obra bibliográfica eliminada correctamente.',
        ]);
    }

    private function normalizeRelations(array $payload): array
    {
        if (! empty($payload['biblioteca_subcategoria_id'])) {
            $subcategory = BibliotecaSubcategoria::query()
                ->findOrFail($payload['biblioteca_subcategoria_id']);
            $payload['biblioteca_categoria_id'] = $subcategory->biblioteca_categoria_id;
            $payload['subcategory'] = $subcategory->name;
        } elseif (array_key_exists('biblioteca_subcategoria_id', $payload)) {
            $payload['subcategory'] = null;
        }

        if (! empty($payload['biblioteca_categoria_id'])) {
            $payload['category'] = BibliotecaCategoria::query()
                ->whereKey($payload['biblioteca_categoria_id'])
                ->value('name');
        }

        if (! empty($payload['biblioteca_ubicacion_id'])) {
            $payload['physical_location'] = BibliotecaUbicacion::query()
                ->whereKey($payload['biblioteca_ubicacion_id'])
                ->value('name');
        }

        return $payload;
    }
}
