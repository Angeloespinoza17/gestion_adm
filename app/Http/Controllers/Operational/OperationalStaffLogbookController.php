<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\ListOperationalStaffLogEntriesRequest;
use App\Http\Requests\Operational\SaveOperationalStaffLogEntryRequest;
use App\Models\Operational\OperationalStaffLogEntry;
use App\Models\User;
use App\Services\Operational\OperationalStaffLogbookAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class OperationalStaffLogbookController extends Controller
{
    public function __construct(
        private readonly OperationalStaffLogbookAccessService $access,
    ) {}

    public function index(ListOperationalStaffLogEntriesRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();
        $query = OperationalStaffLogEntry::query()
            ->select([
                'id', 'owner_user_id', 'staff_id', 'owner_name_snapshot', 'occurred_at',
                'category', 'custom_category', 'title', 'details', 'created_at', 'updated_at',
            ])
            ->with([
                'owner:id,name,staff_id',
                'owner.staff:id,full_name,cargo_id',
                'owner.staff.cargo:id,name',
            ])
            ->visibleTo($user);

        $this->applyFilters($query, $filters, $user->isSuperAdmin());

        $todayTotal = (clone $query)
            ->whereBetween('occurred_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();
        $staffTotal = (clone $query)->distinct()->count('owner_user_id');

        $paginator = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);
        $paginator->setCollection($paginator->getCollection()->map(
            fn (OperationalStaffLogEntry $entry): array => $this->presentEntry($entry, $user),
        ));

        return response()->json(array_merge($paginator->toArray(), [
            'summary' => [
                'total' => $paginator->total(),
                'today' => $todayTotal,
                'staff' => $staffTotal,
            ],
            'categories' => OperationalStaffLogEntry::CATEGORY_OPTIONS,
            'scope' => [
                'mode' => $user->isSuperAdmin() ? 'all' : 'own',
                'is_superadmin' => $user->isSuperAdmin(),
                'can_create' => $this->access->canCreate($user),
                'privacy_note' => $user->isSuperAdmin()
                    ? 'Consulta institucional de todas las bitácoras de funcionarios.'
                    : 'Tu bitácora se mantiene en un espacio interno y protegido.',
            ],
            'staff' => $user->isSuperAdmin() ? $this->staffCatalog() : [],
        ]))->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function store(SaveOperationalStaffLogEntryRequest $request): JsonResponse
    {
        $entry = new OperationalStaffLogEntry($this->normalizedPayload($request->validated()));
        $entry->owner_user_id = $request->user()->id;
        $entry->staff_id = $request->user()->staff_id;
        $entry->owner_name_snapshot = $this->ownerName($request->user());
        $entry->save();

        $entry->load(['owner:id,name,staff_id', 'owner.staff:id,full_name,cargo_id', 'owner.staff.cargo:id,name']);

        return response()->json([
            'message' => 'Registro agregado a tu bitácora.',
            'data' => $this->presentEntry($entry, $request->user()),
        ], 201)->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function update(
        SaveOperationalStaffLogEntryRequest $request,
        OperationalStaffLogEntry $entry,
    ): JsonResponse {
        abort_unless($this->access->canUpdate($request->user(), $entry), 403);

        $entry->fill($this->normalizedPayload($request->validated()));
        $entry->save();
        $entry->load(['owner:id,name,staff_id', 'owner.staff:id,full_name,cargo_id', 'owner.staff.cargo:id,name']);

        return response()->json([
            'message' => 'Registro de bitácora actualizado.',
            'data' => $this->presentEntry($entry, $request->user()),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, bool $isSuperAdmin): void
    {
        $query
            ->when(filled($filters['category'] ?? null), fn (Builder $builder) => $builder->where('category', $filters['category']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $builder) => $builder->where('occurred_at', '>=', Carbon::parse($filters['date_from'])->startOfDay()))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $builder) => $builder->where('occurred_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()))
            ->when(
                $isSuperAdmin && filled($filters['owner_user_id'] ?? null),
                fn (Builder $builder) => $builder->where('owner_user_id', $filters['owner_user_id']),
            )
            ->when(filled($filters['search'] ?? null), function (Builder $builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%")
                        ->orWhere('custom_category', 'like', "%{$search}%")
                        ->orWhere('owner_name_snapshot', 'like', "%{$search}%");
                });
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $payload): array
    {
        if (($payload['category'] ?? null) !== 'other') {
            $payload['custom_category'] = null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEntry(OperationalStaffLogEntry $entry, User $viewer): array
    {
        $ownerName = $entry->owner?->staff?->full_name
            ?: $entry->owner?->name
            ?: $entry->owner_name_snapshot;

        return [
            'id' => $entry->id,
            'occurred_at' => $entry->occurred_at?->format('Y-m-d H:i:s'),
            'category' => $entry->category,
            'category_label' => $entry->categoryLabel(),
            'custom_category' => $entry->custom_category,
            'title' => $entry->title,
            'details' => $entry->details,
            'created_at' => $entry->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $entry->updated_at?->format('Y-m-d H:i:s'),
            'was_edited' => $entry->created_at && $entry->updated_at && ! $entry->created_at->equalTo($entry->updated_at),
            'can_edit' => $this->access->canUpdate($viewer, $entry),
            'owner' => [
                'id' => $entry->owner_user_id,
                'name' => $ownerName,
                'position' => $entry->owner?->staff?->cargo?->name,
            ],
        ];
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function staffCatalog(): array
    {
        $ownerIds = OperationalStaffLogEntry::query()
            ->whereNotNull('owner_user_id')
            ->distinct()
            ->pluck('owner_user_id');

        return User::query()
            ->with('staff:id,full_name')
            ->whereIn('id', $ownerIds)
            ->orderBy('name')
            ->get(['id', 'name', 'staff_id'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->staff?->full_name ?: $user->name,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function ownerName(User $user): string
    {
        $user->loadMissing('staff:id,full_name');

        return $user->staff?->full_name ?: $user->name;
    }
}
