<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveNewsPostRequest;
use App\Models\NewsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $category = trim((string) $request->query('category'));
        $featured = $request->query('featured');

        $query = NewsPost::query()
            ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('author_role', 'like', "%{$search}%")
                        ->orWhere('quote_text', 'like', "%{$search}%")
                        ->orWhere('info_box_title', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($category !== '', fn ($builder) => $builder->where('category', $category));

        if ($featured !== null && $featured !== '') {
            $query->where('featured', filter_var($featured, FILTER_VALIDATE_BOOLEAN));
        }

        $items = $query
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 12), 50));

        return response()->json($items);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'statuses' => [
                ['value' => NewsPost::STATUS_DRAFT, 'label' => 'Borrador'],
                ['value' => NewsPost::STATUS_PUBLISHED, 'label' => 'Publicado'],
                ['value' => NewsPost::STATUS_ARCHIVED, 'label' => 'Archivado'],
            ],
            'categories' => NewsPost::query()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'stats' => [
                'total' => NewsPost::query()->count(),
                'published' => NewsPost::query()->where('status', NewsPost::STATUS_PUBLISHED)->count(),
                'draft' => NewsPost::query()->where('status', NewsPost::STATUS_DRAFT)->count(),
                'featured' => NewsPost::query()->where('featured', true)->count(),
                'views' => (int) NewsPost::query()->sum('views_count'),
            ],
        ]);
    }

    public function show(NewsPost $newsPost): JsonResponse
    {
        return response()->json([
            'data' => $newsPost->load(['createdBy:id,name,email', 'updatedBy:id,name,email']),
        ]);
    }

    public function store(SaveNewsPostRequest $request): JsonResponse
    {
        $payload = $this->payload($request);
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $newsPost = NewsPost::query()->create($payload);

        if ($request->file('image') instanceof UploadedFile) {
            $this->storeImage($newsPost, $request->file('image'));
        }

        return response()->json([
            'message' => 'Noticia creada correctamente.',
            'data' => $newsPost->fresh(['createdBy:id,name,email', 'updatedBy:id,name,email']),
        ], 201);
    }

    public function update(SaveNewsPostRequest $request, NewsPost $newsPost): JsonResponse
    {
        $payload = $this->payload($request, $newsPost);
        $payload['updated_by'] = $request->user()?->id;

        if ($request->boolean('remove_image') && $newsPost->image_path) {
            Storage::disk('public')->delete($newsPost->image_path);
            $payload['image_path'] = null;
        }

        $newsPost->update($payload);

        if ($request->file('image') instanceof UploadedFile) {
            if ($newsPost->image_path) {
                Storage::disk('public')->delete($newsPost->image_path);
            }
            $this->storeImage($newsPost, $request->file('image'));
        }

        return response()->json([
            'message' => 'Noticia actualizada correctamente.',
            'data' => $newsPost->fresh(['createdBy:id,name,email', 'updatedBy:id,name,email']),
        ]);
    }

    public function destroy(NewsPost $newsPost): JsonResponse
    {
        if ($newsPost->image_path) {
            Storage::disk('public')->delete($newsPost->image_path);
        }

        Storage::disk('public')->deleteDirectory("news/{$newsPost->id}");
        $newsPost->delete();

        return response()->json([
            'message' => 'Noticia eliminada correctamente.',
        ]);
    }

    private function payload(SaveNewsPostRequest $request, ?NewsPost $newsPost = null): array
    {
        $payload = $request->validated();
        unset($payload['image'], $payload['remove_image']);

        $payload['slug'] = $this->uniqueSlug(
            $payload['slug'] ?: $payload['title'],
            $newsPost?->id,
        );
        $payload['featured'] = filter_var($payload['featured'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload['share_enabled'] = filter_var($payload['share_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $payload['sort_order'] = (int) ($payload['sort_order'] ?? 0);
        $payload['reading_minutes'] = $payload['reading_minutes'] ?? null;
        $payload['secondary_image_position'] = $payload['secondary_image_position'] ?? 'right';

        foreach ($this->nullableStringFields() as $field) {
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

        if ($payload['reading_minutes'] === '') {
            $payload['reading_minutes'] = null;
        }

        if ($payload['secondary_image_position'] === null) {
            $payload['secondary_image_position'] = 'right';
        }

        if (! empty($payload['body'])) {
            $payload['body'] = NewsPost::sanitizeHtml($payload['body']);
        }

        if (array_key_exists('detail_categories', $payload)) {
            $payload['detail_categories'] = $this->cleanStringList($payload['detail_categories'] ?? []);
        }

        if (array_key_exists('toc_items', $payload)) {
            $payload['toc_items'] = $this->cleanTocItems($payload['toc_items'] ?? []);
        }

        if (array_key_exists('feature_points', $payload)) {
            $payload['feature_points'] = $this->cleanIconCards($payload['feature_points'] ?? []);
        }

        if (array_key_exists('comparison_cards', $payload)) {
            $payload['comparison_cards'] = $this->cleanComparisonCards($payload['comparison_cards'] ?? []);
        }

        if (array_key_exists('key_principles', $payload)) {
            $payload['key_principles'] = $this->cleanKeyPrinciples($payload['key_principles'] ?? []);
        }

        if (array_key_exists('future_trends', $payload)) {
            $payload['future_trends'] = $this->cleanIconCards($payload['future_trends'] ?? []);
        }

        if (array_key_exists('tags', $payload)) {
            $payload['tags'] = $this->cleanStringList($payload['tags'] ?? []);
        }

        if (($payload['status'] ?? null) === NewsPost::STATUS_PUBLISHED && empty($payload['published_at'])) {
            $payload['published_at'] = now();
        }

        if (($payload['status'] ?? null) !== NewsPost::STATUS_PUBLISHED && empty($payload['published_at'])) {
            $payload['published_at'] = null;
        }

        return $payload;
    }

    private function nullableStringFields(): array
    {
        return [
            'excerpt',
            'body',
            'category',
            'author_name',
            'author_role',
            'external_image_url',
            'image_alt',
            'header_image_url',
            'author_image_url',
            'author_image_alt',
            'comments_label',
            'quote_text',
            'quote_author',
            'secondary_section_title',
            'secondary_image_url',
            'secondary_image_alt',
            'secondary_image_caption',
            'secondary_image_position',
            'info_box_icon',
            'info_box_title',
            'info_box_text',
            'published_at',
        ];
    }

    private function cleanStringList(array $items): ?array
    {
        $items = collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function cleanTocItems(array $items): ?array
    {
        $items = collect($items)
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];
                $label = trim((string) ($item['label'] ?? ''));
                $anchor = trim((string) ($item['anchor'] ?? ''));

                return [
                    'label' => $label,
                    'anchor' => Str::slug($anchor ?: $label),
                ];
            })
            ->filter(fn ($item) => $item['label'] !== '')
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function cleanIconCards(array $items): ?array
    {
        $items = collect($items)
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'icon' => trim((string) ($item['icon'] ?? '')),
                    'title' => trim((string) ($item['title'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item['icon'] !== '' || $item['title'] !== '' || $item['description'] !== '')
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function cleanComparisonCards(array $items): ?array
    {
        $items = collect($items)
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];
                $list = $item['items'] ?? [];

                if (is_string($list)) {
                    $list = preg_split('/\r\n|\r|\n/', $list) ?: [];
                }

                $list = collect(is_array($list) ? $list : [])
                    ->map(fn ($point) => trim((string) $point))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'icon' => trim((string) ($item['icon'] ?? '')),
                    'title' => trim((string) ($item['title'] ?? '')),
                    'items' => $list,
                ];
            })
            ->filter(fn ($item) => $item['icon'] !== '' || $item['title'] !== '' || $item['items'] !== [])
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function cleanKeyPrinciples(array $items): ?array
    {
        $items = collect($items)
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'number' => trim((string) ($item['number'] ?? '')),
                    'title' => trim((string) ($item['title'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item['number'] !== '' || $item['title'] !== '' || $item['description'] !== '')
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'noticia';
        $slug = $base;
        $counter = 2;

        while (
            NewsPost::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function storeImage(NewsPost $newsPost, UploadedFile $image): void
    {
        $path = $image->store("news/{$newsPost->id}", 'public');

        $newsPost->forceFill([
            'image_path' => $path,
            'external_image_url' => null,
        ])->save();
    }
}
