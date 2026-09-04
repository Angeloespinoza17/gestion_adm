<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStudentLifePostRequest;
use App\Http\Resources\StudentLifePostResource;
use App\Models\NewsPost;
use App\Models\StudentLifePost;
use App\Models\StudentLifePostImage;
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

class StudentLifePostController extends Controller
{
    private const MAX_GALLERY_IMAGES = 12;

    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $category = trim((string) $request->query('category'));
        $active = $this->booleanQuery($request, 'active');
        $featured = $this->booleanQuery($request, 'featured');

        $query = StudentLifePost::query()
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
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, StudentLifePost::STATUSES, true), fn ($builder) => $builder->where('status', $status))
            ->when($category !== '', fn ($builder) => $builder->where('category', $category))
            ->when($active !== null, fn ($builder) => $builder->where('active', $active))
            ->when($featured !== null, fn ($builder) => $builder->where('featured', $featured));

        $items = $query
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('featured')
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('event_date')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return StudentLifePostResource::collection($items);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $stats = StudentLifePost::query()
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
            'categories' => StudentLifePost::query()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'stats' => [
                'total' => (int) ($stats?->total ?? 0),
                'published' => (int) ($stats?->published ?? 0),
                'draft' => (int) ($stats?->draft ?? 0),
                'active' => (int) ($stats?->active ?? 0),
                'featured' => (int) ($stats?->featured ?? 0),
            ],
            'capabilities' => [
                'can_manage' => (bool) $request->user()?->hasPermission('gestionar_vida_estudiantil'),
            ],
        ]);
    }

    public function show(StudentLifePost $studentLifePost): StudentLifePostResource
    {
        return new StudentLifePostResource(
            $studentLifePost->load([
                'galleryImages',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ]),
        );
    }

    public function store(SaveStudentLifePostRequest $request): JsonResponse
    {
        $storedPaths = [];

        try {
            $post = DB::transaction(function () use ($request, &$storedPaths): StudentLifePost {
                $payload = $this->payload($request);
                $payload['created_by'] = $request->user()?->id;
                $payload['updated_by'] = $request->user()?->id;

                $post = StudentLifePost::query()->create($payload);

                if ($request->file('cover_image') instanceof UploadedFile) {
                    $path = $request->file('cover_image')->store("site/student-life/{$post->id}/cover", 'public');
                    $storedPaths[] = $path;
                    $post->forceFill([
                        'cover_image_path' => $path,
                        'external_cover_image_url' => null,
                    ])->save();
                }

                $this->storeGalleryImages($request, $post, $storedPaths, 1);

                return $post;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        return response()->json([
            'message' => 'Contenido de vida estudiantil creado correctamente.',
            'data' => (new StudentLifePostResource(
                $post->fresh()->load([
                    'galleryImages',
                    'createdBy:id,name,email',
                    'updatedBy:id,name,email',
                ]),
            ))->resolve($request),
        ], 201);
    }

    public function update(
        SaveStudentLifePostRequest $request,
        StudentLifePost $studentLifePost,
    ): JsonResponse {
        $removeImages = $this->galleryImagesToRemove($request, $studentLifePost);
        $newFiles = collect($request->file('gallery', []))->filter();
        $retainedCount = $studentLifePost->galleryImages()->count() - $removeImages->count();

        if ($retainedCount + $newFiles->count() > self::MAX_GALLERY_IMAGES) {
            throw ValidationException::withMessages([
                'gallery' => 'La galería puede contener un máximo de 12 imágenes.',
            ]);
        }

        $oldCoverPath = $studentLifePost->cover_image_path;
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $studentLifePost,
                $removeImages,
                &$storedPaths,
            ): void {
                $payload = $this->payload($request, $studentLifePost);
                $payload['updated_by'] = $request->user()?->id;

                if ($request->boolean('remove_cover_image')) {
                    $payload['cover_image_path'] = null;
                }

                if ($request->file('cover_image') instanceof UploadedFile) {
                    $path = $request->file('cover_image')->store(
                        "site/student-life/{$studentLifePost->id}/cover",
                        'public',
                    );
                    $storedPaths[] = $path;
                    $payload['cover_image_path'] = $path;
                    $payload['external_cover_image_url'] = null;
                }

                $studentLifePost->update($payload);
                StudentLifePostImage::query()
                    ->whereIn('id', $removeImages->pluck('id'))
                    ->delete();

                $nextOrder = ((int) $studentLifePost->galleryImages()->max('sort_order')) + 1;
                $this->storeGalleryImages($request, $studentLifePost, $storedPaths, max(1, $nextOrder));
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        $freshPost = $studentLifePost->fresh();

        if ($oldCoverPath && $oldCoverPath !== $freshPost->cover_image_path) {
            Storage::disk('public')->delete($oldCoverPath);
        }

        Storage::disk('public')->delete($removeImages->pluck('image_path')->filter()->all());

        return response()->json([
            'message' => 'Contenido de vida estudiantil actualizado correctamente.',
            'data' => (new StudentLifePostResource(
                $freshPost->load([
                    'galleryImages',
                    'createdBy:id,name,email',
                    'updatedBy:id,name,email',
                ]),
            ))->resolve($request),
        ]);
    }

    public function destroy(StudentLifePost $studentLifePost): JsonResponse
    {
        $directory = "site/student-life/{$studentLifePost->id}";

        DB::transaction(fn () => $studentLifePost->delete());
        Storage::disk('public')->deleteDirectory($directory);

        return response()->json([
            'message' => 'Contenido de vida estudiantil eliminado correctamente.',
        ]);
    }

    private function payload(
        SaveStudentLifePostRequest $request,
        ?StudentLifePost $post = null,
    ): array {
        $payload = $request->validated();
        unset(
            $payload['cover_image'],
            $payload['remove_cover_image'],
            $payload['gallery'],
            $payload['gallery_alts'],
            $payload['remove_gallery_image_ids'],
        );

        $slugSource = trim((string) ($payload['slug'] ?? '')) ?: (string) $payload['title'];
        $payload['slug'] = $this->uniqueSlug($slugSource, $post?->id);

        foreach ([
            'category',
            'summary',
            'body',
            'external_cover_image_url',
            'cover_image_alt',
            'event_date',
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

        $payload['active'] = array_key_exists('active', $payload)
            ? (bool) $payload['active']
            : ($post?->active ?? true);
        $payload['featured'] = array_key_exists('featured', $payload)
            ? (bool) $payload['featured']
            : ($post?->featured ?? false);
        $payload['sort_order'] = (int) ($payload['sort_order'] ?? $post?->sort_order ?? 0);

        if (($payload['status'] ?? null) === StudentLifePost::STATUS_PUBLISHED && empty($payload['published_at'])) {
            $payload['published_at'] = $post?->published_at ?? now();
        }

        if (($payload['status'] ?? null) !== StudentLifePost::STATUS_PUBLISHED) {
            $payload['published_at'] = null;
        }

        return $payload;
    }

    private function storeGalleryImages(
        SaveStudentLifePostRequest $request,
        StudentLifePost $post,
        array &$storedPaths,
        int $startOrder,
    ): void {
        $files = collect($request->file('gallery', []))->filter()->values();
        $alts = collect($request->input('gallery_alts', []));

        $files->each(function (UploadedFile $image, int $index) use (
            $post,
            $alts,
            &$storedPaths,
            $startOrder,
        ): void {
            $path = $image->store("site/student-life/{$post->id}/gallery", 'public');
            $storedPaths[] = $path;

            $post->galleryImages()->create([
                'image_path' => $path,
                'alt_text' => trim((string) $alts->get($index)) ?: null,
                'sort_order' => $startOrder + $index,
            ]);
        });
    }

    private function galleryImagesToRemove(
        SaveStudentLifePostRequest $request,
        StudentLifePost $post,
    ): Collection {
        $ids = collect($request->input('remove_gallery_image_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return $post->galleryImages()
            ->whereIn('id', $ids)
            ->get(['id', 'student_life_post_id', 'image_path']);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'vida-estudiantil';
        $slug = $base;
        $counter = 2;

        while (
            StudentLifePost::query()
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
            ['value' => StudentLifePost::STATUS_DRAFT, 'label' => 'Borrador'],
            ['value' => StudentLifePost::STATUS_PUBLISHED, 'label' => 'Publicado'],
            ['value' => StudentLifePost::STATUS_ARCHIVED, 'label' => 'Archivado'],
        ];
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
