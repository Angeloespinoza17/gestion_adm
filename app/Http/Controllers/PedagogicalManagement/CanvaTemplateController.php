<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\ListCanvaTemplatesRequest;
use App\Http\Requests\PedagogicalManagement\ValidateCanvaTemplateRequest;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaAutofillAccessPolicy;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaConnectionLocator;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTemplateFieldMapper;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTemplateService;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationAccessService;
use Illuminate\Http\JsonResponse;

class CanvaTemplateController extends Controller
{
    public function index(
        ListCanvaTemplatesRequest $request,
        ClassPresentationAccessService $access,
        CanvaConnectionLocator $connections,
        CanvaTemplateService $templates,
        CanvaAutofillAccessPolicy $autofillAccess,
    ): JsonResponse {
        $data = $request->validated();
        $schoolId = (int) $data['school_id'];
        abort_unless($access->canAccessSchool($request->user(), $schoolId), 403);
        $connection = $connections->active($request->user(), $schoolId);
        $autofillAccess->assertAvailable($connection);
        $payload = $templates->list($connection, [
            'query' => $data['query'] ?? null,
            'continuation' => $data['continuation'] ?? null,
            'limit' => (int) ($data['limit'] ?? 25),
        ]);

        return response()->json(['data' => [
            'items' => collect((array) ($payload['items'] ?? []))
                ->map(fn (mixed $item): array => $this->template((array) $item))
                ->values()
                ->all(),
            'continuation' => $payload['continuation'] ?? null,
        ]]);
    }

    public function validateTemplate(
        ValidateCanvaTemplateRequest $request,
        string $brandTemplateId,
        ClassPresentationAccessService $access,
        CanvaConnectionLocator $connections,
        CanvaTemplateService $templates,
        CanvaTemplateFieldMapper $mapper,
        CanvaAutofillAccessPolicy $autofillAccess,
    ): JsonResponse {
        $data = $request->validated();
        $schoolId = (int) $data['school_id'];
        abort_unless($access->canAccessSchool($request->user(), $schoolId), 403);
        $connection = $connections->active($request->user(), $schoolId);
        $autofillAccess->assertAvailable($connection);

        return response()->json(['data' => $mapper->validate(
            $templates->dataset($connection, $brandTemplateId),
            (int) $data['slide_count'],
        )]);
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function template(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($item['title'] ?? $item['name'] ?? 'Plantilla Canva'),
            'view_url' => $this->safeCanvaUrl($item['view_url'] ?? null),
            'create_url' => $this->safeCanvaUrl($item['create_url'] ?? null),
            'thumbnail_url' => $this->safeCanvaUrl(data_get($item, 'thumbnail.url')),
            'thumbnail' => $this->safeCanvaUrl(data_get($item, 'thumbnail.url')) ? [
                'url' => $this->safeCanvaUrl(data_get($item, 'thumbnail.url')),
                'width' => data_get($item, 'thumbnail.width'),
                'height' => data_get($item, 'thumbnail.height'),
            ] : null,
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
        ];
    }

    private function safeCanvaUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($url === '' || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return null;
        }

        return ($host === 'canva.com' || str_ends_with($host, '.canva.com')) ? mb_substr($url, 0, 4000) : null;
    }
}
