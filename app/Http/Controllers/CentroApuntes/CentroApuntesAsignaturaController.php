<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroApuntes\SaveCentroApuntesAsignaturaRequest;
use App\Models\CentroApuntes\CentroApuntesAsignatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CentroApuntesAsignaturaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CentroApuntesAsignatura::class);

        $search = trim((string) $request->query('search'));
        $items = CentroApuntesAsignatura::query()
            ->withCount('solicitudes')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('area', 'like', "%{$search}%")
                        ->orWhere('education_level', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('area'), fn ($builder) => $builder->where('area', $request->query('area')))
            ->when($request->filled('education_level'), fn ($builder) => $builder->where('education_level', $request->query('education_level')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function store(SaveCentroApuntesAsignaturaRequest $request): JsonResponse
    {
        $this->authorize('create', CentroApuntesAsignatura::class);

        $subject = CentroApuntesAsignatura::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        ));

        return response()->json([
            'message' => 'Asignatura registrada correctamente.',
            'data' => $subject,
        ], 201);
    }

    public function update(SaveCentroApuntesAsignaturaRequest $request, CentroApuntesAsignatura $subject): JsonResponse
    {
        $this->authorize('update', $subject);

        $subject->fill(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id]
        ))->save();

        return response()->json([
            'message' => 'Asignatura actualizada correctamente.',
            'data' => $subject->fresh(),
        ]);
    }

    public function destroy(CentroApuntesAsignatura $subject): JsonResponse
    {
        $this->authorize('delete', $subject);

        if ($subject->solicitudes()->exists()) {
            throw ValidationException::withMessages([
                'subject' => 'No se puede eliminar una asignatura con solicitudes asociadas.',
            ]);
        }

        $subject->delete();

        return response()->json([
            'message' => 'Asignatura eliminada correctamente.',
        ]);
    }
}
