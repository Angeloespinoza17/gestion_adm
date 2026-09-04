<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaEjemplarRequest;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaUbicacion;
use App\Services\Library\BibliotecaCodeService;
use App\Services\Library\BibliotecaInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BibliotecaInventoryController extends Controller
{
    public function __construct(
        private readonly BibliotecaInventoryService $inventoryService,
        private readonly BibliotecaCodeService $codeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaEjemplar::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaEjemplar::query()
            ->with(['obra:id,title,main_author,cover_image_url,internal_code,material_type,category', 'registeredBy:id,name', 'ubicacion:id,name,code'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('obra', function ($obraQuery) use ($search) {
                            $obraQuery
                                ->where('title', 'like', "%{$search}%")
                                ->orWhere('main_author', 'like', "%{$search}%")
                                ->orWhere('isbn', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('biblioteca_obra_id'), fn ($builder) => $builder->where('biblioteca_obra_id', $request->query('biblioteca_obra_id')))
            ->when($request->filled('physical_state'), fn ($builder) => $builder->where('physical_state', $request->query('physical_state')))
            ->when($request->filled('availability_status'), fn ($builder) => $builder->where('availability_status', $request->query('availability_status')))
            ->when($request->filled('biblioteca_ubicacion_id'), fn ($builder) => $builder->where('biblioteca_ubicacion_id', $request->query('biblioteca_ubicacion_id')))
            ->when($request->filled('physical_location'), fn ($builder) => $builder->where('physical_location', 'like', '%'.$request->query('physical_location').'%'))
            ->when($request->boolean('only_active', true), fn ($builder) => $builder->where('is_active', true));

        $currentYear = now()->year;

        return response()->json([
            'items' => $query->orderBy('code')->paginate((int) $request->query('per_page', 15)),
            'summary' => [
                'active_total' => BibliotecaEjemplar::query()->where('is_active', true)->count(),
                'checked_this_year' => BibliotecaEjemplar::query()->whereYear('last_inventory_checked_at', $currentYear)->count(),
                'pending_check' => BibliotecaEjemplar::query()->where(function ($builder) use ($currentYear) {
                    $builder->whereNull('last_inventory_checked_at')->orWhereYear('last_inventory_checked_at', '<', $currentYear);
                })->count(),
                'damaged_or_lost' => BibliotecaEjemplar::query()->whereIn('availability_status', ['danado', 'perdido'])->count(),
            ],
        ]);
    }

    public function show(BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('view', $ejemplar);

        return response()->json([
            'data' => $ejemplar->load([
                'obra',
                'movimientos.responsible:id,name',
                'prestamos.obra:id,title',
                'prestamos.deliveredBy:id,name',
                'prestamos.receivedBy:id,name',
                'reservas.obra:id,title',
            ]),
        ]);
    }

    public function store(SaveBibliotecaEjemplarRequest $request): JsonResponse
    {
        $this->authorize('update', BibliotecaEjemplar::class);

        $ejemplar = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $validated['code'] = ($validated['code'] ?? null) ?: $this->codeService->next('EJ');
            if (! empty($validated['biblioteca_ubicacion_id'])) {
                $validated['physical_location'] = BibliotecaUbicacion::query()
                    ->whereKey($validated['biblioteca_ubicacion_id'])
                    ->value('name');
            }
            $ejemplar = BibliotecaEjemplar::query()->create(array_merge(
                $validated,
                [
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]
            ));

            $this->inventoryService->moveEjemplar(
                $ejemplar->fresh(['obra']),
                $request->user(),
                'alta',
                [],
                'Alta de ejemplar.',
                ['movement_date' => now()]
            );

            return $ejemplar->fresh(['obra']);
        });

        return response()->json([
            'message' => 'Ejemplar registrado correctamente.',
            'data' => $ejemplar,
        ], 201);
    }

    public function update(SaveBibliotecaEjemplarRequest $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $previousPhotoUrls = (array) ($ejemplar->photo_urls ?? []);
        $changes = $request->validated();
        if (! empty($changes['biblioteca_ubicacion_id'])) {
            $changes['physical_location'] = BibliotecaUbicacion::query()
                ->whereKey($changes['biblioteca_ubicacion_id'])
                ->value('name');
        }
        $movementType = 'ajuste';

        if (($changes['physical_location'] ?? $ejemplar->physical_location) !== $ejemplar->physical_location) {
            $movementType = 'cambio_ubicacion';
        } elseif (($changes['physical_state'] ?? $ejemplar->physical_state) !== $ejemplar->physical_state
            || ($changes['availability_status'] ?? $ejemplar->availability_status) !== $ejemplar->availability_status) {
            $movementType = 'cambio_estado';
        }

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            $movementType,
            array_merge($changes, ['updated_by' => $request->user()->id]),
            'Actualización de ejemplar.',
            ['movement_date' => now()]
        );

        if (array_key_exists('photo_urls', $changes)) {
            $this->deleteRemovedManagedPhotos(
                $ejemplar,
                $previousPhotoUrls,
                (array) ($changes['photo_urls'] ?? [])
            );
        }

        return response()->json([
            'message' => 'Ejemplar actualizado correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    public function uploadPhotos(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);
        $payload = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:6'],
            'photos.*' => ['required', 'file', 'max:10240'],
        ]);
        $currentUrls = collect((array) ($ejemplar->photo_urls ?? []))
            ->filter(fn ($url): bool => is_string($url) && $url !== '')
            ->unique()
            ->values();
        $photos = collect($payload['photos']);

        if ($currentUrls->count() + $photos->count() > 12) {
            throw ValidationException::withMessages([
                'photos' => 'Cada ejemplar admite un máximo de 12 fotografías de evidencia.',
            ]);
        }

        $storedPaths = [];
        $newUrls = [];

        try {
            foreach ($photos as $photo) {
                $extension = $this->extensionForPhotoMime((string) $photo->getMimeType());
                if (! $extension) {
                    throw ValidationException::withMessages([
                        'photos' => 'Las fotografías deben estar en formato JPG, PNG, WebP, HEIC o HEIF.',
                    ]);
                }

                $filename = Str::uuid()->toString().'.'.$extension;
                $directory = 'library/inventory-evidence/'.$ejemplar->id;
                $path = $photo->storeAs($directory, $filename, 'local');
                if (! $path) {
                    throw ValidationException::withMessages([
                        'photos' => 'No fue posible almacenar una de las fotografías.',
                    ]);
                }
                $storedPaths[] = $path;
                $newUrls[] = $this->managedPhotoUrl($ejemplar, $filename);
            }

            DB::transaction(function () use ($ejemplar, $newUrls, $request): void {
                $locked = BibliotecaEjemplar::query()->lockForUpdate()->findOrFail($ejemplar->id);
                $lockedUrls = collect((array) ($locked->photo_urls ?? []))
                    ->filter(fn ($url): bool => is_string($url) && $url !== '')
                    ->merge($newUrls)
                    ->unique()
                    ->values();
                if ($lockedUrls->count() > 12) {
                    throw ValidationException::withMessages([
                        'photos' => 'Cada ejemplar admite un máximo de 12 fotografías de evidencia.',
                    ]);
                }

                $this->inventoryService->moveEjemplar(
                    $locked->fresh(['obra']),
                    $request->user(),
                    'ajuste',
                    ['photo_urls' => $lockedUrls->all()],
                    count($newUrls) === 1
                        ? 'Evidencia fotográfica incorporada desde Inventario.'
                        : 'Evidencias fotográficas incorporadas desde Inventario.',
                    ['movement_date' => now(), 'photo_count_added' => count($newUrls)]
                );
            }, 3);
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        return response()->json([
            'message' => count($newUrls) === 1
                ? 'Fotografía incorporada al inventario.'
                : 'Fotografías incorporadas al inventario.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    public function photo(BibliotecaEjemplar $ejemplar, string $photo)
    {
        $this->authorize('view', $ejemplar);
        $expectedUrl = $this->managedPhotoUrl($ejemplar, $photo);
        abort_unless(in_array($expectedUrl, (array) ($ejemplar->photo_urls ?? []), true), 404);

        $path = 'library/inventory-evidence/'.$ejemplar->id.'/'.$photo;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $photo, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="'.$photo.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function audit(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate([
            'physical_count_status' => ['required', 'string', 'max:80'],
            'physical_location' => ['nullable', 'string', 'max:120'],
            'physical_state' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'inventario_fisico',
            [
                'physical_location' => $payload['physical_location'] ?? $ejemplar->physical_location,
                'physical_state' => $payload['physical_state'] ?? $ejemplar->physical_state,
                'last_inventory_checked_at' => now()->format('Y-m-d'),
            ],
            $payload['notes'] ?? 'Inventario físico anual.',
            [
                'physical_count_status' => $payload['physical_count_status'],
                'movement_date' => now(),
            ]
        );

        return response()->json([
            'message' => 'Inventario físico registrado correctamente.',
            'data' => $ejemplar->fresh(['obra', 'movimientos']),
        ]);
    }

    public function markDamage(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'danio',
            [
                'physical_state' => 'danado',
                'availability_status' => 'danado',
                'damaged_at' => now(),
            ],
            $payload['notes'] ?? 'Daño registrado.',
            ['movement_date' => now()]
        );

        return response()->json([
            'message' => 'Daño registrado correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    public function markLoss(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'perdida',
            [
                'physical_state' => 'perdido',
                'availability_status' => 'perdido',
                'lost_at' => now(),
                'is_active' => false,
            ],
            $payload['notes'] ?? 'Pérdida registrada.',
            ['movement_date' => now()]
        );

        return response()->json([
            'message' => 'Pérdida registrada correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    public function deactivate(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'baja',
            [
                'physical_state' => 'dado_de_baja',
                'availability_status' => 'dado_de_baja',
                'withdrawn_at' => now(),
                'is_active' => false,
            ],
            $payload['notes'] ?? 'Baja de ejemplar.',
            ['movement_date' => now()]
        );

        return response()->json([
            'message' => 'Ejemplar dado de baja correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    /** @param array<int, mixed> $previousUrls
     * @param  array<int, mixed>  $currentUrls
     */
    private function deleteRemovedManagedPhotos(BibliotecaEjemplar $ejemplar, array $previousUrls, array $currentUrls): void
    {
        collect(array_diff($previousUrls, $currentUrls))
            ->filter(fn ($url): bool => is_string($url))
            ->each(function (string $url) use ($ejemplar): void {
                $prefix = '/api/biblioteca/ejemplares/'.$ejemplar->id.'/photos/';
                if (! str_starts_with($url, $prefix)) {
                    return;
                }

                $filename = basename($url);
                if (preg_match('/^[a-f0-9-]+\.(?:jpe?g|png|webp|heic|heif)$/i', $filename) !== 1) {
                    return;
                }

                Storage::disk('local')->delete('library/inventory-evidence/'.$ejemplar->id.'/'.$filename);
            });
    }

    private function managedPhotoUrl(BibliotecaEjemplar $ejemplar, string $filename): string
    {
        return '/api/biblioteca/ejemplares/'.$ejemplar->id.'/photos/'.$filename;
    }

    private function extensionForPhotoMime(string $mime): ?string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic', 'image/x-heic' => 'heic',
            'image/heif', 'image/x-heif' => 'heif',
            default => null,
        };
    }
}
