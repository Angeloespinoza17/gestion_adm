<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaObraRequest;
use App\Models\Library\BibliotecaObra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BibliotecaCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaObra::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaObra::query()
            ->with(['recommendedCourse:id,display_name'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('main_author', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('internal_code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('material_type'), fn ($builder) => $builder->where('material_type', $request->query('material_type')))
            ->when($request->filled('category'), fn ($builder) => $builder->where('category', $request->query('category')))
            ->when($request->filled('recommended_course_section_id'), fn ($builder) => $builder->where('recommended_course_section_id', $request->query('recommended_course_section_id')))
            ->when($request->filled('general_status'), fn ($builder) => $builder->where('general_status', $request->query('general_status')))
            ->when($request->filled('physical_location'), fn ($builder) => $builder->where('physical_location', 'like', '%' . $request->query('physical_location') . '%'))
            ->when($request->boolean('available_only'), fn ($builder) => $builder->where('available_copies', '>', 0));

        return response()->json($query->orderBy('title')->paginate((int) $request->query('per_page', 12)));
    }

    public function show(BibliotecaObra $obra): JsonResponse
    {
        $this->authorize('view', $obra);

        return response()->json([
            'data' => $obra->load([
                'recommendedCourse:id,display_name',
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

        $obra = BibliotecaObra::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        ));

        return response()->json([
            'message' => 'Obra bibliográfica registrada correctamente.',
            'data' => $obra->fresh(['recommendedCourse:id,display_name']),
        ], 201);
    }

    public function update(SaveBibliotecaObraRequest $request, BibliotecaObra $obra): JsonResponse
    {
        $this->authorize('update', $obra);

        $obra->fill(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id]
        ))->save();

        return response()->json([
            'message' => 'Obra bibliográfica actualizada correctamente.',
            'data' => $obra->fresh(['recommendedCourse:id,display_name']),
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
}
