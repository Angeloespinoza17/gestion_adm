<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Porter\StorePorterDailyLogEntryRequest;
use App\Models\PorterDailyLogEntry;
use App\Services\Porter\PorterAccessService;
use App\Services\Porter\PorterAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PorterDailyLogEntryController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
        private readonly PorterAuditService $auditService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search'));
        $category = trim((string) $request->query('category'));
        $priority = trim((string) $request->query('priority'));

        $query = PorterDailyLogEntry::query()
            ->with(['registeredBy:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('detail', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn (Builder $query) => $query->where('category', $category))
            ->when($priority !== '', fn (Builder $query) => $query->where('priority', $priority))
            ->when($request->query('date_from'), fn (Builder $query, $value) => $query->whereDate('logged_on', '>=', $value))
            ->when($request->query('date_to'), fn (Builder $query, $value) => $query->whereDate('logged_on', '<=', $value));

        return response()->json(
            $query->latest('logged_at')->latest('id')->paginate((int) $request->query('per_page', 15))
        );
    }

    public function store(StorePorterDailyLogEntryRequest $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('registrar_bitacora_porteria') || $request->user()?->isSuperAdmin(), 403);

        $entry = PorterDailyLogEntry::create([
            'registered_by' => $request->user()?->id,
            'logged_on' => now()->toDateString(),
            'logged_at' => now(),
            'shift_label' => $request->input('shift_label'),
            'category' => $request->input('category'),
            'priority' => $request->input('priority'),
            'status' => $request->input('status', 'registrado'),
            'title' => $request->string('title')->toString(),
            'detail' => $request->string('detail')->toString(),
        ]);

        $this->auditService->log(
            $entry,
            'registro_bitacora',
            null,
            $entry->status,
            'Entrada de bitácora registrada.',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Entrada de bitácora registrada correctamente.',
            'data' => $entry->fresh(['registeredBy:id,name']),
        ], 201);
    }
}
