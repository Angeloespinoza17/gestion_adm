<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\BibliotecaLectorTemporal;
use App\Models\Library\BibliotecaPrestamo;
use App\Support\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BibliotecaTemporaryBorrowerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaPrestamo::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaLectorTemporal::query()
            ->withCount(['loans as active_loans_count' => fn ($builder) => $builder->whereIn('status', ['activo', 'renovado', 'vencido'])])
            ->when($request->boolean('only_active', true), fn ($builder) => $builder->where('active', true))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhere('course_name', 'like', "%{$search}%")
                        ->orWhere('person_category', 'like', "%{$search}%");
                });
            });

        return response()->json($query->orderBy('full_name')->paginate((int) $request->query('per_page', 30)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BibliotecaPrestamo::class);

        $payload = $this->validated($request);
        $payload['rut'] = Rut::normalize($payload['rut'] ?? null);
        $payload['created_by'] = $request->user()->id;
        $payload['updated_by'] = $request->user()->id;

        $reader = BibliotecaLectorTemporal::query()->create($payload);

        return response()->json([
            'message' => 'Lector temporal registrado correctamente.',
            'data' => $reader,
        ], 201);
    }

    public function update(Request $request, BibliotecaLectorTemporal $lectorTemporal): JsonResponse
    {
        $this->authorize('create', BibliotecaPrestamo::class);

        $payload = $this->validated($request, true);
        if (array_key_exists('rut', $payload)) {
            $payload['rut'] = Rut::normalize($payload['rut']);
        }
        $payload['updated_by'] = $request->user()->id;
        $lectorTemporal->update($payload);

        return response()->json([
            'message' => 'Lector temporal actualizado correctamente.',
            'data' => $lectorTemporal->fresh(),
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'full_name' => [$required, 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:30'],
            'person_category' => [$required, Rule::in(BibliotecaLectorTemporal::PERSON_CATEGORIES)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'course_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
