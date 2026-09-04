<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderSiteInstallationsRequest;
use App\Http\Requests\SaveSiteInstallationRequest;
use App\Http\Resources\SiteInstallationResource;
use App\Models\NewsPost;
use App\Models\SiteInstallation;
use App\Models\SiteInstallationImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SiteInstallationController extends Controller
{
    private const MAX_GALLERY_IMAGES = 12;

    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $category = trim((string) $request->query('category'));
        $active = $this->booleanQuery($request, 'active');
        $featured = $this->booleanQuery($request, 'featured');

        $query = SiteInstallation::query()
            ->with([
                'galleryImages',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('location_label', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($status, SiteInstallation::STATUSES, true),
                fn ($builder) => $builder->where('status', $status),
            )
            ->when($category !== '', fn ($builder) => $builder->where('category', $category))
            ->when($active !== null, fn ($builder) => $builder->where('active', $active))
            ->when($featured !== null, fn ($builder) => $builder->where('featured', $featured));

        $items = $query
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('featured')
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return SiteInstallationResource::collection($items);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $stats = SiteInstallation::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN status = 'published' AND active = 1 AND published_at IS NOT NULL AND published_at <= ? THEN 1 ELSE 0 END) as published",
                [now()],
            )
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft")
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured')
            ->first();

        return response()->json([
            'statuses' => $this->statuses(),
            'categories' => SiteInstallation::query()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'icons' => collect(SiteInstallation::ICONS)
                ->map(fn (array $icon, string $value): array => [
                    'value' => $value,
                    'label' => $icon['label'],
                    'class_name' => $icon['class'],
                ])
                ->values(),
            'stats' => [
                'total' => (int) ($stats?->total ?? 0),
                'published' => (int) ($stats?->published ?? 0),
                'draft' => (int) ($stats?->draft ?? 0),
                'active' => (int) ($stats?->active ?? 0),
                'featured' => (int) ($stats?->featured ?? 0),
            ],
            'capabilities' => [
                'can_manage' => (bool) $request->user()?->hasPermission(
                    'gestionar_instalaciones_sitio',
                ),
            ],
        ]);
    }

    public function show(SiteInstallation $siteInstallation): SiteInstallationResource
    {
        return new SiteInstallationResource(
            $siteInstallation->load([
                'galleryImages',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ]),
        );
    }

    public function store(SaveSiteInstallationRequest $request): JsonResponse
    {
        $storedPaths = [];

        try {
            $installation = DB::transaction(function () use ($request, &$storedPaths): SiteInstallation {
                $payload = $this->payload($request);
                $payload['created_by'] = $request->user()?->id;
                $payload['updated_by'] = $request->user()?->id;

                $installation = SiteInstallation::query()->create($payload);

                if ($request->file('cover_image') instanceof UploadedFile) {
                    $path = $request->file('cover_image')->store(
                        "site/installations/{$installation->id}/cover",
                        'public',
                    );
                    $storedPaths[] = $path;
                    $installation->forceFill(['cover_image_path' => $path])->save();
                }

                $this->storeGalleryImages($request, $installation, $storedPaths, 1);
                $this->ensurePublishable($installation->fresh());

                return $installation;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        return response()->json([
            'message' => 'Instalación creada correctamente.',
            'data' => $this->resource($installation->fresh(), $request),
        ], 201);
    }

    public function update(
        SaveSiteInstallationRequest $request,
        SiteInstallation $siteInstallation,
    ): JsonResponse {
        $removeImages = $this->galleryImagesToRemove($request, $siteInstallation);
        $newFiles = collect($request->file('gallery', []))->filter();
        $retainedCount = $siteInstallation->galleryImages()->count() - $removeImages->count();

        if ($retainedCount + $newFiles->count() > self::MAX_GALLERY_IMAGES) {
            throw ValidationException::withMessages([
                'gallery' => 'La galería puede contener un máximo de 12 imágenes.',
            ]);
        }

        $oldCoverPath = $siteInstallation->cover_image_path;
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $siteInstallation,
                $removeImages,
                &$storedPaths,
            ): void {
                $payload = $this->payload($request, $siteInstallation);
                $payload['updated_by'] = $request->user()?->id;

                if ($request->boolean('remove_cover_image')) {
                    $payload['cover_image_path'] = null;
                    $payload['cover_image_alt'] = null;
                }

                if ($request->file('cover_image') instanceof UploadedFile) {
                    $path = $request->file('cover_image')->store(
                        "site/installations/{$siteInstallation->id}/cover",
                        'public',
                    );
                    $storedPaths[] = $path;
                    $payload['cover_image_path'] = $path;
                }

                $siteInstallation->update($payload);
                SiteInstallationImage::query()
                    ->whereIn('id', $removeImages->pluck('id'))
                    ->delete();

                $nextOrder = ((int) $siteInstallation->galleryImages()->max('sort_order')) + 1;
                $this->storeGalleryImages(
                    $request,
                    $siteInstallation,
                    $storedPaths,
                    max(1, $nextOrder),
                );
                $this->reorderGallery($request, $siteInstallation);
                $this->ensurePublishable($siteInstallation->fresh());
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        $freshInstallation = $siteInstallation->fresh();

        if ($oldCoverPath && $oldCoverPath !== $freshInstallation->cover_image_path) {
            Storage::disk('public')->delete($oldCoverPath);
        }

        Storage::disk('public')->delete($removeImages->pluck('image_path')->filter()->all());

        return response()->json([
            'message' => 'Instalación actualizada correctamente.',
            'data' => $this->resource($freshInstallation, $request),
        ]);
    }

    public function reorder(ReorderSiteInstallationsRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('items') as $item) {
                SiteInstallation::query()
                    ->whereKey($item['id'])
                    ->update([
                        'sort_order' => $item['sort_order'],
                        'updated_by' => $request->user()?->id,
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json([
            'message' => 'Orden de las instalaciones actualizado correctamente.',
        ]);
    }

    public function destroy(SiteInstallation $siteInstallation): JsonResponse
    {
        $directory = "site/installations/{$siteInstallation->id}";

        DB::transaction(fn () => $siteInstallation->delete());
        Storage::disk('public')->deleteDirectory($directory);

        return response()->json([
            'message' => 'Instalación eliminada correctamente.',
        ]);
    }

    private function payload(
        SaveSiteInstallationRequest $request,
        ?SiteInstallation $installation = null,
    ): array {
        $payload = $request->validated();
        unset(
            $payload['cover_image'],
            $payload['remove_cover_image'],
            $payload['gallery'],
            $payload['gallery_alts'],
            $payload['remove_gallery_image_ids'],
            $payload['gallery_order'],
        );

        $slugSource = trim((string) ($payload['slug'] ?? '')) ?: (string) $payload['title'];
        $payload['slug'] = $this->uniqueSlug($slugSource, $installation?->id);

        foreach ([
            'category',
            'summary',
            'body',
            'location_label',
            'accessibility_notes',
            'icon',
            'cover_image_alt',
            'meta_title',
            'meta_description',
            'published_at',
        ] as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            if (is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }

            if ($payload[$field] === '') {
                $payload[$field] = null;
            }
        }

        if (array_key_exists('body', $payload)) {
            $payload['body'] = NewsPost::sanitizeHtml($payload['body']);
        }

        if (array_key_exists('features', $payload)) {
            $payload['features'] = collect($payload['features'] ?? [])
                ->map(fn ($feature) => trim((string) $feature))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $payload['icon'] = $payload['icon'] ?? $installation?->icon ?? 'buildings';
        $payload['active'] = array_key_exists('active', $payload)
            ? (bool) $payload['active']
            : ($installation?->active ?? true);
        $payload['featured'] = array_key_exists('featured', $payload)
            ? (bool) $payload['featured']
            : ($installation?->featured ?? false);
        $payload['sort_order'] = (int) (
            $payload['sort_order'] ?? $installation?->sort_order ?? 0
        );

        if (
            ($payload['status'] ?? null) === SiteInstallation::STATUS_PUBLISHED
            && empty($payload['published_at'])
        ) {
            $payload['published_at'] = $installation?->published_at ?? now();
        }

        if (($payload['status'] ?? null) !== SiteInstallation::STATUS_PUBLISHED) {
            $payload['published_at'] = null;
        }

        return $payload;
    }

    private function ensurePublishable(SiteInstallation $installation): void
    {
        if ($installation->status !== SiteInstallation::STATUS_PUBLISHED) {
            return;
        }

        if (! $installation->cover_image_path) {
            throw ValidationException::withMessages([
                'cover_image' => 'Debes agregar una imagen de portada antes de publicar la instalación.',
            ]);
        }

        if (! trim((string) $installation->cover_image_alt)) {
            throw ValidationException::withMessages([
                'cover_image_alt' => 'Describe la imagen de portada antes de publicar la instalación.',
            ]);
        }

        $hasGalleryImageWithoutAlt = $installation->galleryImages()
            ->where(function ($query): void {
                $query->whereNull('alt_text')->orWhere('alt_text', '');
            })
            ->exists();

        if ($hasGalleryImageWithoutAlt) {
            throw ValidationException::withMessages([
                'gallery_alts' => 'Cada imagen de la galería debe tener una descripción antes de publicar.',
            ]);
        }
    }

    private function storeGalleryImages(
        SaveSiteInstallationRequest $request,
        SiteInstallation $installation,
        array &$storedPaths,
        int $startOrder,
    ): void {
        $files = collect($request->file('gallery', []))->filter()->values();
        $alts = collect($request->input('gallery_alts', []));

        $files->each(function (UploadedFile $image, int $index) use (
            $installation,
            $alts,
            &$storedPaths,
            $startOrder,
        ): void {
            $path = $image->store(
                "site/installations/{$installation->id}/gallery",
                'public',
            );
            $storedPaths[] = $path;

            $installation->galleryImages()->create([
                'image_path' => $path,
                'alt_text' => trim((string) $alts->get($index)) ?: null,
                'sort_order' => $startOrder + $index,
            ]);
        });
    }

    private function galleryImagesToRemove(
        SaveSiteInstallationRequest $request,
        SiteInstallation $installation,
    ): Collection {
        $ids = collect($request->input('remove_gallery_image_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $images = $installation->galleryImages()
            ->whereIn('id', $ids)
            ->get(['id', 'site_installation_id', 'image_path']);

        if ($images->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'remove_gallery_image_ids' => 'Una de las imágenes no pertenece a esta instalación.',
            ]);
        }

        return $images;
    }

    private function reorderGallery(
        SaveSiteInstallationRequest $request,
        SiteInstallation $installation,
    ): void {
        $requestedIds = collect($request->input('gallery_order', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            return;
        }

        $existingIds = $installation->galleryImages()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');

        if ($requestedIds->diff($existingIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'gallery_order' => 'Una de las imágenes no pertenece a esta instalación.',
            ]);
        }

        $orderedIds = $requestedIds
            ->concat($existingIds->diff($requestedIds))
            ->values();

        foreach ($orderedIds as $index => $imageId) {
            SiteInstallationImage::query()
                ->where('site_installation_id', $installation->id)
                ->whereKey($imageId)
                ->update(['sort_order' => $index + 1]);
        }
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'instalacion';
        $slug = $base;
        $counter = 2;

        while (
            SiteInstallation::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function statuses(): array
    {
        return [
            ['value' => SiteInstallation::STATUS_DRAFT, 'label' => 'Borrador'],
            ['value' => SiteInstallation::STATUS_PUBLISHED, 'label' => 'Publicado'],
            ['value' => SiteInstallation::STATUS_ARCHIVED, 'label' => 'Archivado'],
        ];
    }

    private function resource(SiteInstallation $installation, Request $request): array
    {
        return (new SiteInstallationResource(
            $installation->load([
                'galleryImages',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ]),
        ))->resolve($request);
    }

    private function booleanQuery(Request $request, string $key): ?bool
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->query('per_page', 12), 50));
    }
}
