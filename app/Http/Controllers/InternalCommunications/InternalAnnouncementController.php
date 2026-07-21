<?php

namespace App\Http\Controllers\InternalCommunications;

use App\Http\Controllers\Controller;
use App\Http\Requests\InternalCommunications\SaveInternalAnnouncementRequest;
use App\Models\InternalCommunications\InternalAnnouncement;
use App\Models\InternalCommunications\InternalAnnouncementRead;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalAnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $priority = trim((string) $request->query('priority'));
        $category = trim((string) $request->query('category'));
        $roleId = (int) $request->query('role_id');

        $items = InternalAnnouncement::query()
            ->with(['roles:id,name,slug', 'createdBy:id,name,email', 'updatedBy:id,name,email'])
            ->withCount([
                'reads as read_count' => fn (Builder $query) => $query->whereNotNull('read_at'),
                'reads as acknowledged_count' => fn (Builder $query) => $query->whereNotNull('acknowledged_at'),
            ])
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($priority !== '', fn (Builder $builder) => $builder->where('priority', $priority))
            ->when($category !== '', fn (Builder $builder) => $builder->where('category', $category))
            ->when($roleId > 0, fn (Builder $builder) => $builder->whereHas('roles', fn (Builder $roles) => $roles->whereKey($roleId)))
            ->orderByDesc('pinned')
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'important' THEN 1 ELSE 2 END")
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 12), 50));

        return response()->json($items);
    }

    public function catalogs(Request $request): JsonResponse
    {
        return response()->json([
            'statuses' => [
                ['value' => InternalAnnouncement::STATUS_DRAFT, 'label' => 'Borrador'],
                ['value' => InternalAnnouncement::STATUS_PUBLISHED, 'label' => 'Publicado'],
                ['value' => InternalAnnouncement::STATUS_ARCHIVED, 'label' => 'Archivado'],
            ],
            'priorities' => [
                ['value' => InternalAnnouncement::PRIORITY_NORMAL, 'label' => 'Normal'],
                ['value' => InternalAnnouncement::PRIORITY_IMPORTANT, 'label' => 'Importante'],
                ['value' => InternalAnnouncement::PRIORITY_URGENT, 'label' => 'Urgente'],
            ],
            'roles' => Role::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'categories' => InternalAnnouncement::query()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'stats' => [
                'total' => InternalAnnouncement::query()->count(),
                'published' => InternalAnnouncement::query()->where('status', InternalAnnouncement::STATUS_PUBLISHED)->count(),
                'active' => InternalAnnouncement::query()->published()->count(),
                'draft' => InternalAnnouncement::query()->where('status', InternalAnnouncement::STATUS_DRAFT)->count(),
            ],
            'capabilities' => [
                'can_manage' => $request->user()?->hasPermission('gestionar_comunicaciones_internas') ?? false,
            ],
        ]);
    }

    public function show(InternalAnnouncement $internalAnnouncement): JsonResponse
    {
        return response()->json([
            'data' => $internalAnnouncement->load([
                'roles:id,name,slug',
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
                'reads.user:id,name,email',
            ]),
        ]);
    }

    public function store(SaveInternalAnnouncementRequest $request): JsonResponse
    {
        $payload = $this->payload($request);
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $announcement = InternalAnnouncement::query()->create($payload);
        $announcement->roles()->sync($request->boolean('audience_all') ? [] : $request->input('role_ids', []));

        return response()->json([
            'message' => 'Comunicacion interna creada correctamente.',
            'data' => $announcement->fresh(['roles:id,name,slug', 'createdBy:id,name,email', 'updatedBy:id,name,email']),
        ], 201);
    }

    public function update(SaveInternalAnnouncementRequest $request, InternalAnnouncement $internalAnnouncement): JsonResponse
    {
        $payload = $this->payload($request);
        $payload['updated_by'] = $request->user()?->id;

        $internalAnnouncement->update($payload);
        $internalAnnouncement->roles()->sync($request->boolean('audience_all') ? [] : $request->input('role_ids', []));

        return response()->json([
            'message' => 'Comunicacion interna actualizada correctamente.',
            'data' => $internalAnnouncement->fresh(['roles:id,name,slug', 'createdBy:id,name,email', 'updatedBy:id,name,email']),
        ]);
    }

    public function destroy(InternalAnnouncement $internalAnnouncement): JsonResponse
    {
        $internalAnnouncement->delete();

        return response()->json([
            'message' => 'Comunicacion interna eliminada correctamente.',
        ]);
    }

    public function markRead(Request $request, InternalAnnouncement $internalAnnouncement): JsonResponse
    {
        $user = $request->user();

        $isVisible = InternalAnnouncement::query()
            ->visibleToUser($user)
            ->whereKey($internalAnnouncement->id)
            ->exists();

        if (! $isVisible) {
            abort(404);
        }

        $read = InternalAnnouncementRead::query()->firstOrNew([
            'internal_announcement_id' => $internalAnnouncement->id,
            'user_id' => $user->id,
        ]);

        if (! $read->read_at) {
            $read->read_at = now();
        }

        if ($request->boolean('acknowledged')) {
            $read->acknowledged_at = now();
        }

        $read->save();

        return response()->json([
            'message' => $read->acknowledged_at ? 'Recepcion confirmada.' : 'Aviso marcado como leido.',
            'data' => [
                'read_at' => $read->read_at?->toIso8601String(),
                'acknowledged_at' => $read->acknowledged_at?->toIso8601String(),
            ],
        ]);
    }

    private function payload(SaveInternalAnnouncementRequest $request): array
    {
        $payload = $request->validated();
        unset($payload['role_ids']);

        foreach (['title', 'body', 'category'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        if (($payload['category'] ?? '') === '') {
            $payload['category'] = null;
        }

        $payload['pinned'] = filter_var($payload['pinned'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload['audience_all'] = filter_var($payload['audience_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload['requires_ack'] = filter_var($payload['requires_ack'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (($payload['status'] ?? null) === InternalAnnouncement::STATUS_PUBLISHED && empty($payload['published_at'])) {
            $payload['published_at'] = now();
        }

        if (($payload['status'] ?? null) === InternalAnnouncement::STATUS_DRAFT && empty($payload['published_at'])) {
            $payload['published_at'] = null;
        }

        return $payload;
    }
}
