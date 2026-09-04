<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTestimonialRequest;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TestimonialController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $active = $this->booleanQuery($request, 'active');
        $featured = $this->booleanQuery($request, 'featured');

        $query = Testimonial::query()
            ->with([
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
                'consentConfirmedBy:id,name',
            ])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('author_name', 'like', "%{$search}%")
                        ->orWhere('author_role', 'like', "%{$search}%")
                        ->orWhere('quote', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, Testimonial::STATUSES, true), fn ($builder) => $builder->where('status', $status))
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

        return TestimonialResource::collection($items);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $stats = Testimonial::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN status = 'published' AND active = 1 AND consent_confirmed_at IS NOT NULL AND published_at IS NOT NULL AND published_at <= ? THEN 1 ELSE 0 END) as published",
                [now()],
            )
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft")
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured')
            ->first();

        return response()->json([
            'statuses' => $this->statuses(),
            'stats' => [
                'total' => (int) ($stats?->total ?? 0),
                'published' => (int) ($stats?->published ?? 0),
                'draft' => (int) ($stats?->draft ?? 0),
                'active' => (int) ($stats?->active ?? 0),
                'featured' => (int) ($stats?->featured ?? 0),
            ],
            'capabilities' => [
                'can_manage' => (bool) $request->user()?->hasPermission('gestionar_testimonios'),
            ],
        ]);
    }

    public function show(Testimonial $testimonial): TestimonialResource
    {
        return new TestimonialResource(
            $testimonial->load([
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
                'consentConfirmedBy:id,name',
            ]),
        );
    }

    public function store(SaveTestimonialRequest $request): JsonResponse
    {
        $storedPath = null;

        try {
            $testimonial = DB::transaction(function () use ($request, &$storedPath): Testimonial {
                $payload = $this->payload($request);
                $payload['created_by'] = $request->user()?->id;
                $payload['updated_by'] = $request->user()?->id;
                $this->applyConsent($payload, $request);

                $testimonial = Testimonial::query()->create($payload);

                if ($request->file('image') instanceof UploadedFile) {
                    $storedPath = $request->file('image')->store("site/testimonials/{$testimonial->id}", 'public');
                    $testimonial->forceFill([
                        'image_path' => $storedPath,
                        'external_image_url' => null,
                    ])->save();
                }

                return $testimonial;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Testimonio creado correctamente.',
            'data' => (new TestimonialResource(
                $testimonial->fresh([
                    'createdBy:id,name,email',
                    'updatedBy:id,name,email',
                    'consentConfirmedBy:id,name',
                ]),
            ))->resolve($request),
        ], 201);
    }

    public function update(SaveTestimonialRequest $request, Testimonial $testimonial): JsonResponse
    {
        $oldPath = $testimonial->image_path;
        $newPath = null;

        try {
            DB::transaction(function () use ($request, $testimonial, &$newPath): void {
                $payload = $this->payload($request, $testimonial);
                $payload['updated_by'] = $request->user()?->id;
                $this->applyConsent($payload, $request, $testimonial);

                if ($request->boolean('remove_image')) {
                    $payload['image_path'] = null;
                }

                if ($request->file('image') instanceof UploadedFile) {
                    $newPath = $request->file('image')->store("site/testimonials/{$testimonial->id}", 'public');
                    $payload['image_path'] = $newPath;
                    $payload['external_image_url'] = null;
                }

                $testimonial->update($payload);
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath && $oldPath !== $testimonial->fresh()->image_path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Testimonio actualizado correctamente.',
            'data' => (new TestimonialResource(
                $testimonial->fresh([
                    'createdBy:id,name,email',
                    'updatedBy:id,name,email',
                    'consentConfirmedBy:id,name',
                ]),
            ))->resolve($request),
        ]);
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $directory = "site/testimonials/{$testimonial->id}";

        DB::transaction(fn () => $testimonial->delete());
        Storage::disk('public')->deleteDirectory($directory);

        return response()->json([
            'message' => 'Testimonio eliminado correctamente.',
        ]);
    }

    private function payload(SaveTestimonialRequest $request, ?Testimonial $testimonial = null): array
    {
        $payload = $request->validated();
        unset($payload['image'], $payload['remove_image'], $payload['authorization_confirmed']);

        foreach (['quote', 'author_name', 'author_role', 'external_image_url', 'image_alt', 'published_at'] as $field) {
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

        $payload['active'] = array_key_exists('active', $payload)
            ? (bool) $payload['active']
            : ($testimonial?->active ?? true);
        $payload['featured'] = array_key_exists('featured', $payload)
            ? (bool) $payload['featured']
            : ($testimonial?->featured ?? false);
        $payload['sort_order'] = (int) ($payload['sort_order'] ?? $testimonial?->sort_order ?? 0);

        if (($payload['status'] ?? null) === Testimonial::STATUS_PUBLISHED && empty($payload['published_at'])) {
            $payload['published_at'] = $testimonial?->published_at ?? now();
        }

        if (($payload['status'] ?? null) !== Testimonial::STATUS_PUBLISHED) {
            $payload['published_at'] = null;
        }

        return $payload;
    }

    private function applyConsent(
        array &$payload,
        SaveTestimonialRequest $request,
        ?Testimonial $testimonial = null,
    ): void {
        if (! $request->exists('authorization_confirmed')) {
            return;
        }

        if ($request->boolean('authorization_confirmed')) {
            if ($testimonial?->consent_confirmed_at) {
                return;
            }

            $payload['consent_confirmed_at'] = now();
            $payload['consent_confirmed_by'] = $request->user()?->id;

            return;
        }

        $payload['consent_confirmed_at'] = null;
        $payload['consent_confirmed_by'] = null;
    }

    private function statuses(): array
    {
        return [
            ['value' => Testimonial::STATUS_DRAFT, 'label' => 'Borrador'],
            ['value' => Testimonial::STATUS_PUBLISHED, 'label' => 'Publicado'],
            ['value' => Testimonial::STATUS_ARCHIVED, 'label' => 'Archivado'],
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
