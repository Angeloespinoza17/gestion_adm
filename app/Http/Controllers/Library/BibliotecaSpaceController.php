<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaEspacioRequest;
use App\Http\Requests\Library\SaveBibliotecaUsoEspacioRequest;
use App\Models\Library\BibliotecaEspacio;
use App\Models\Library\BibliotecaUsoEspacio;
use App\Services\Library\BibliotecaSpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaSpaceController extends Controller
{
    public function __construct(
        private readonly BibliotecaSpaceService $spaceService,
    ) {
    }

    public function spaces(): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaEspacio::class);

        return response()->json([
            'data' => BibliotecaEspacio::query()->withCount('usos')->orderBy('name')->get(),
        ]);
    }

    public function storeSpace(SaveBibliotecaEspacioRequest $request): JsonResponse
    {
        $this->authorize('create', BibliotecaEspacio::class);

        return response()->json([
            'message' => 'Espacio guardado correctamente.',
            'data' => $this->spaceService->storeSpace($request->validated(), $request->user()),
        ]);
    }

    public function updateSpace(SaveBibliotecaEspacioRequest $request, BibliotecaEspacio $espacio): JsonResponse
    {
        $this->authorize('update', $espacio);

        $payload = array_merge($request->validated(), ['id' => $espacio->id, 'name' => $request->validated()['name']]);

        return response()->json([
            'message' => 'Espacio actualizado correctamente.',
            'data' => $this->spaceService->storeSpace($payload, $request->user()),
        ]);
    }

    public function usages(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaUsoEspacio::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaUsoEspacio::query()
            ->with(['espacio:id,name', 'courseSection:id,display_name', 'responsibleStaff:id,full_name', 'requestedBy:id,name'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('activity_type', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('biblioteca_espacio_id'), fn ($builder) => $builder->where('biblioteca_espacio_id', $request->query('biblioteca_espacio_id')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('course_section_id'), fn ($builder) => $builder->where('course_section_id', $request->query('course_section_id')))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('start_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('start_at', '<=', $request->query('date_to')));

        return response()->json($query->latest('start_at')->paginate((int) $request->query('per_page', 15)));
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaUsoEspacio::class);

        $items = BibliotecaUsoEspacio::query()
            ->with('espacio:id,name')
            ->when($request->filled('biblioteca_espacio_id'), fn ($builder) => $builder->where('biblioteca_espacio_id', $request->query('biblioteca_espacio_id')))
            ->get()
            ->map(fn (BibliotecaUsoEspacio $usage) => [
                'id' => $usage->id,
                'title' => sprintf('%s · %s', $usage->espacio?->name ?? 'Espacio', $usage->title),
                'start' => optional($usage->start_at)->toIso8601String(),
                'end' => optional($usage->end_at)->toIso8601String(),
                'extendedProps' => [
                    'status' => $usage->status,
                    'activity_type' => $usage->activity_type,
                ],
            ]);

        return response()->json(['data' => $items]);
    }

    public function storeUsage(SaveBibliotecaUsoEspacioRequest $request): JsonResponse
    {
        $this->authorize('create', BibliotecaUsoEspacio::class);

        return response()->json([
            'message' => 'Uso de espacio registrado correctamente.',
            'data' => $this->spaceService->storeUsage($request->validated(), $request->user()),
        ], 201);
    }

    public function updateUsage(SaveBibliotecaUsoEspacioRequest $request, BibliotecaUsoEspacio $usoEspacio): JsonResponse
    {
        $this->authorize('update', $usoEspacio);

        return response()->json([
            'message' => 'Uso de espacio actualizado correctamente.',
            'data' => $this->spaceService->updateUsage($usoEspacio, $request->validated(), $request->user()),
        ]);
    }

    public function transition(Request $request, BibliotecaUsoEspacio $usoEspacio, string $status): JsonResponse
    {
        $this->authorize('update', $usoEspacio);

        abort_unless(in_array($status, BibliotecaUsoEspacio::STATUS_OPTIONS, true), 404);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        return response()->json([
            'message' => 'Estado de uso de espacio actualizado correctamente.',
            'data' => $this->spaceService->transition($usoEspacio, $status, $request->user(), $payload['notes'] ?? null),
        ]);
    }
}
