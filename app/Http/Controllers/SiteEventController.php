<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSiteEventRequest;
use App\Models\NewsPost;
use App\Models\SiteEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $category = trim((string) $request->query('category'));
        $featured = $request->query('featured');

        $query = SiteEvent::query()
            ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('organizer_name', 'like', "%{$search}%")
                        ->orWhere('organizer_email', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($category !== '', fn ($builder) => $builder->where('category', $category));

        if ($featured !== null && $featured !== '') {
            $query->where('featured', filter_var($featured, FILTER_VALIDATE_BOOLEAN));
        }

        $items = $query
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 12), 50));

        return response()->json($items);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'statuses' => [
                ['value' => SiteEvent::STATUS_DRAFT, 'label' => 'Borrador'],
                ['value' => SiteEvent::STATUS_PUBLISHED, 'label' => 'Publicado'],
                ['value' => SiteEvent::STATUS_ARCHIVED, 'label' => 'Archivado'],
            ],
            'categories' => SiteEvent::query()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'stats' => [
                'total' => SiteEvent::query()->count(),
                'published' => SiteEvent::query()->where('status', SiteEvent::STATUS_PUBLISHED)->count(),
                'draft' => SiteEvent::query()->where('status', SiteEvent::STATUS_DRAFT)->count(),
                'featured' => SiteEvent::query()->where('featured', true)->count(),
            ],
        ]);
    }

    public function show(SiteEvent $siteEvent): JsonResponse
    {
        return response()->json([
            'data' => $siteEvent->load(['createdBy:id,name,email', 'updatedBy:id,name,email']),
        ]);
    }

    public function store(SaveSiteEventRequest $request): JsonResponse
    {
        $payload = $this->payload($request);
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $siteEvent = SiteEvent::query()->create($payload);

        return response()->json([
            'message' => 'Evento creado correctamente.',
            'data' => $siteEvent->fresh(['createdBy:id,name,email', 'updatedBy:id,name,email']),
        ], 201);
    }

    public function update(SaveSiteEventRequest $request, SiteEvent $siteEvent): JsonResponse
    {
        $payload = $this->payload($request, $siteEvent);
        $payload['updated_by'] = $request->user()?->id;

        $siteEvent->update($payload);

        return response()->json([
            'message' => 'Evento actualizado correctamente.',
            'data' => $siteEvent->fresh(['createdBy:id,name,email', 'updatedBy:id,name,email']),
        ]);
    }

    public function destroy(SiteEvent $siteEvent): JsonResponse
    {
        $siteEvent->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente.',
        ]);
    }

    private function payload(SaveSiteEventRequest $request, ?SiteEvent $siteEvent = null): array
    {
        $payload = $request->validated();
        $slugSource = ($payload['slug'] ?? '') !== '' ? $payload['slug'] : $payload['title'];

        $payload['slug'] = $this->uniqueSlug(
            $slugSource,
            $siteEvent?->id,
        );
        $payload['featured'] = filter_var($payload['featured'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload['registration_enabled'] = filter_var($payload['registration_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload['sort_order'] = (int) ($payload['sort_order'] ?? 0);

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

        if (array_key_exists('body', $payload) && ! empty($payload['body'])) {
            $payload['body'] = NewsPost::sanitizeHtml($payload['body']);
        }

        if (array_key_exists('highlights', $payload)) {
            $payload['highlights'] = $this->cleanHighlights($payload['highlights'] ?? []);
        }

        if (array_key_exists('schedule_items', $payload)) {
            $payload['schedule_items'] = $this->cleanScheduleItems($payload['schedule_items'] ?? []);
        }

        if (array_key_exists('gallery_images', $payload)) {
            $payload['gallery_images'] = $this->cleanGalleryImages($payload['gallery_images'] ?? []);
        }

        return $payload;
    }

    private function nullableStringFields(): array
    {
        return [
            'summary',
            'body',
            'category',
            'location',
            'external_url',
            'ends_at',
            'header_image_url',
            'hero_image_url',
            'hero_image_alt',
            'gallery_intro',
            'registration_title',
            'registration_button_label',
            'registration_url',
            'organizer_name',
            'organizer_position',
            'organizer_description',
            'organizer_email',
            'organizer_phone',
            'organizer_image_url',
            'organizer_image_alt',
        ];
    }

    private function cleanHighlights(array $items): ?array
    {
        $items = collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function cleanScheduleItems(array $items): ?array
    {
        $items = collect($items)
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'time' => trim((string) ($item['time'] ?? '')),
                    'title' => trim((string) ($item['title'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item['time'] !== '' || $item['title'] !== '' || $item['description'] !== '')
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function cleanGalleryImages(array $items): ?array
    {
        $items = collect($items)
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'url' => trim((string) ($item['url'] ?? '')),
                    'alt' => trim((string) ($item['alt'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item['url'] !== '')
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'evento';
        $slug = $base;
        $counter = 2;

        while (
            SiteEvent::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
